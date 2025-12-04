<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

 /**
  * db/install.php para logstore_tsdb.
  *
  * Estado atual do processo de inicialização:
  * - Lê as credenciais do TimescaleDB externo a partir de settings do plugin.
  * - Garante o schema 'public' e ajusta o search_path para o schema alvo.
  * - Cria/valida a tabela 'public.<dbtable>' com colunas esperadas para eventos do Moodle.
  * - Se a extensão TimescaleDB estiver ativa no banco, converte a tabela em hypertable via
  *   create_hypertable('<schema>.<tabela>'::regclass, 'time'), confirma em timescaledb_information.hypertables,
  *   habilita compressão e adiciona uma política de compressão de 2 dias (add_compression_policy).
  * - Cria índices úteis: em 'time' e em '(time, userid)'.
  *
  * Itens deliberadamente não implementados neste arquivo: política de retenção, continuous aggregates
  * e funções auxiliares de relatório. Podem ser adicionados futuramente conforme necessidade.
  *
  * Observação importante: Não existe upgrade.php neste plugin. Este arquivo é executado apenas na
  * instalação inicial do plugin. Em upgrades, o Moodle não reaplicará estas alterações automaticamente.
  * Se precisar reaplicar/forçar a inicialização, utilize a página de teste do plugin (test_settings.php)
  * ou execute os comandos SQL manualmente no banco.
  */

// Helpers simples para manter legibilidade sem alterar SQLs.
function logstore_tsdb_quote_ident($name) {
    return '"' . str_replace('"', '""', $name) . '"';
}

function logstore_tsdb_exec($conn, $sql, $okmsg, $failprefix = '[logstore_tsdb] ERRO') {
    $res = pg_query($conn, $sql);
    if ($res === false) {
        error_log($failprefix . ': ' . pg_last_error($conn));
        return false;
    }
    if ($okmsg) {
        error_log($okmsg);
    }
    return true;
}

function xmldb_logstore_tsdb_install() {
    error_log('[logstore_tsdb] install.php executed at ' . date('c'));

    // Config e conexão TSDB externo.
    $cfg = get_config('logstore_tsdb');
    $pghost = trim((string)($cfg->host ?? ''));
    $pgport = trim((string)($cfg->port ?? ''));
    $pgdb   = trim((string)($cfg->database ?? ''));
    $pguser = trim((string)($cfg->username ?? ''));
    $pgpass = (string)($cfg->password ?? '');
    $pgschema = 'public';
    $tableName = trim((string)($cfg->dbtable ?? ''));

    $missing = [];
    if ($pghost === '')    { $missing[] = 'host'; }
    if ($pgport === '')    { $missing[] = 'port'; }
    if ($pgdb === '')      { $missing[] = 'database'; }
    if ($pguser === '')    { $missing[] = 'username'; }
    if ($tableName === '') { $missing[] = 'dbtable'; }
    if (!empty($missing)) {
        error_log('[logstore_tsdb] Configuração incompleta em settings.php; instalação adiada. Campos faltando: ' . implode(', ', $missing));
        return;
    }

    $connstr = 'host=' . $pghost . ' port=' . $pgport . ' dbname=' . $pgdb . ' user=' . $pguser . (strlen($pgpass) ? ' password=' . $pgpass : '');
    $conn = @pg_connect($connstr);
    if (!$conn) {
        error_log('[logstore_tsdb] ERRO: não conectou ao TimescaleDB externo com settings.php.');
        return;
    }
    error_log('[logstore_tsdb] Conectado ao TimescaleDB externo.');

    // Identificadores e search_path.
    $schemaIdent = logstore_tsdb_quote_ident($pgschema);
    $tableIdent  = logstore_tsdb_quote_ident($tableName);
    $qualified   = $schemaIdent . '.' . $tableIdent;
    logstore_tsdb_exec($conn, 'CREATE SCHEMA IF NOT EXISTS ' . $schemaIdent . ';', '[logstore_tsdb] Schema pronto: ' . $schemaIdent);
    logstore_tsdb_exec($conn, 'SET search_path TO ' . $schemaIdent . ';', '[logstore_tsdb] search_path ajustado');

    // CREATE TABLE (mantendo exatamente o mesmo SQL previamente utilizado).
    $createtable = 'CREATE TABLE IF NOT EXISTS ' . $qualified . ' (
        "id" BIGSERIAL PRIMARY KEY,
        "time" TIMESTAMPTZ NOT NULL,
        "eventname" VARCHAR(255) NOT NULL,
        "component" VARCHAR(100),
        "action" VARCHAR(100),
        "target" VARCHAR(100),
        "crud" CHAR(1),
        "edulevel" INT,
        "anonymous" INT DEFAULT 0,
        "courseid" BIGINT,
        "contextid" BIGINT,
        "contextlevel" INT,
        "contextinstanceid" BIGINT,
        "userid" BIGINT,
        "relateduserid" BIGINT,
        "realuserid" BIGINT,
        "objectid" BIGINT,
        "objecttable" VARCHAR(255),
        "timecreated" BIGINT,
        "ip" VARCHAR(45),
        "origin" VARCHAR(20),
        "other" TEXT
    );';
    if (!logstore_tsdb_exec($conn, $createtable, '[logstore_tsdb] Tabela criada/confirmada no TSDB externo: ' . $qualified)) {
        pg_close($conn);
        return;
    }

    // Verifica extensão timescaledb.
    $extRes = pg_query($conn, "SELECT COUNT(*)::int AS cnt FROM pg_extension WHERE extname='timescaledb';");
    $extRow = ($extRes) ? pg_fetch_assoc($extRes) : null;
    $hasTimescale = $extRow && (int)$extRow['cnt'] > 0;

    // Hypertable.
    $isHypertable = false;
    if ($hasTimescale) {
        $qualifiednameplain = $pgschema . '.' . $tableName;
        $hypsql = "SELECT create_hypertable('" . str_replace("'", "''", $qualifiednameplain) . "'::regclass, 'time', chunk_time_interval => INTERVAL '1 day', if_not_exists => TRUE);";
        if (!logstore_tsdb_exec($conn, $hypsql, '[logstore_tsdb] create_hypertable executado para: ' . $qualifiednameplain, '[logstore_tsdb] Aviso: create_hypertable falhou')) {
            // Mantém execução, mas vamos checar existência mais abaixo.
        }

        // Fazemos a verificação se a tabela virou hypertable.
        $checksql = "SELECT 1 FROM timescaledb_information.hypertables WHERE hypertable_schema = '" . str_replace("'", "''", $pgschema) . "' AND hypertable_name = '" . str_replace("'", "''", $tableName) . "'";
        $chkres = pg_query($conn, $checksql);
        $isHypertable = ($chkres && pg_fetch_row($chkres));
        if ($isHypertable) {
            error_log('[logstore_tsdb] Confirmado: tabela é hypertable: ' . $qualifiednameplain);
        } else {
            error_log('[logstore_tsdb] Atenção: tabela NÃO virou hypertable: ' . $qualifiednameplain);
        }
    } else {
        error_log('[logstore_tsdb] Extensão timescaledb não encontrada; seguindo sem hypertable.');
    }

    // Índices para filtros de tempo e usuários.
    logstore_tsdb_exec($conn, "CREATE INDEX IF NOT EXISTS idx_" . str_replace('"', '', $tableName) . "_time ON " . $qualified . " (time DESC);", '[logstore_tsdb] Índice time OK');
    logstore_tsdb_exec($conn, "CREATE INDEX IF NOT EXISTS idx_" . str_replace('"', '', $tableName) . "_userid ON " . $qualified . " (time DESC, userid);", '[logstore_tsdb] Índice userid OK');
    
    // Fazemos a compressão de 2 horas para otimizar o espaço de armazenamento.
    if ($hasTimescale && $isHypertable) {
        logstore_tsdb_exec($conn, "ALTER TABLE " . $qualified . " SET (timescaledb.compress);", '[logstore_tsdb] Compressão habilitada');
        $policysql = "SELECT add_compression_policy('" . str_replace("'", "''", $qualifiednameplain) . "'::regclass, INTERVAL '2 hours');";
        logstore_tsdb_exec($conn, $policysql, '[logstore_tsdb] Política de compressão adicionada (2 horas)');
    } elseif ($hasTimescale) {
        error_log('[logstore_tsdb] Compressão não aplicada porque a tabela não é hypertable.');
    }

    pg_close($conn);
    error_log('[logstore_tsdb] Inicialização concluída no TimescaleDB externo (apenas TSDB).');

}

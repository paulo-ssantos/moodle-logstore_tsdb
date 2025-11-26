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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Languages configuration for the logstore_tsdb plugin.
 *
 * @package   logstore_tsdb
 * @copyright Antônio Neto <antoniocarolino.neto@ucsal.edu.br>
 *            Henrique Viana <henrique.viana@ucsal.edu.br>
 *            Luís Carvalho <luisguilherme.carvalho@ucsal.edu.br>
 *            Paulo Santos <paulovitor.santos@ucsal.edu.br>
 *            Yuri Gomes <yurijesus.gomes@ucsal.edu.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'TimescaleDB log store';

// Settings.
$string['tsdb_type'] = 'Tipo de Banco de Dados';
$string['tsdb_type_help'] = 'Selecione o tipo de banco de dados de séries temporais a ser usado (Apenas TimescaleDB suportado por enquanto)';

$string['host'] = 'Host do Banco de Dados';
$string['host_help'] = 'O nome do host ou endereço IP do servidor de banco de dados';

$string['port'] = 'Porta do Banco de Dados';
$string['port_help'] = 'O número da porta para a conexão com o banco de dados (padrão: 5433 para TimescaleDB)';

$string['database'] = 'Nome do Banco de Dados';
$string['database_help'] = 'O nome do banco de dados para armazenar os logs';

$string['username'] = 'Nome de Usuário do Banco de Dados';
$string['username_help'] = 'O nome de usuário para autenticação no banco de dados';

$string['password'] = 'Senha do Banco de Dados';
$string['password_help'] = 'A senha para autenticação no banco de dados';

$string['databasetable'] = 'Tabela do Banco de Dados';
$string['databasetable_help'] = 'O nome da tabela onde os logs serão armazenados';

$string['writemode'] = 'Modo de Escrita';
$string['writemode_help'] = 'Escolha entre modo de escrita síncrono ou assíncrono';
$string['writemode_sync'] = 'Síncrono (escrita imediata)';
$string['writemode_async'] = 'Assíncrono (escrita em buffer)';

$string['buffersize'] = 'Tamanho do Buffer';
$string['buffersize_help'] = 'Número de entradas de log a serem armazenadas em buffer antes de escrever (aplica-se apenas ao modo assíncrono)';

$string['flushinterval'] = 'Intervalo de Descarga';
$string['flushinterval_help'] = 'Intervalo de tempo em segundos para descarregar logs em buffer (aplica-se apenas ao modo assíncrono)';

// Test settings page.
$string['testsettings'] = 'Testar Conexão TimescaleDB';
$string['currentsettings'] = 'Configuração Atual';
$string['notconfigured'] = 'Não configurado';
$string['connectiontest'] = 'Teste de Conexão';
$string['connectiontest_success'] = 'Conectado com sucesso ao TimescaleDB!';
$string['connectiontest_failed'] = 'Falha ao estabelecer conexão com o TimescaleDB';
$string['connectiontest_exception'] = 'Erro de conexão: {$a}';
$string['postgresversion'] = 'Versão do PostgreSQL: {$a}';
$string['timescaledb_found'] = 'Extensão TimescaleDB encontrada (versão {$a})';
$string['timescaledb_notfound'] = 'Extensão TimescaleDB não instalada (o plugin funcionará com PostgreSQL padrão)';
$string['tabletest'] = 'Teste de Acesso à Tabela';
$string['tabletest_success'] = 'Tabela "{$a->table}" está acessível e contém {$a->count} eventos';
$string['tabletest_failed'] = 'Não foi possível acessar a tabela "{$a->table}": {$a->error}';
$string['buffertest'] = 'Status do Buffer';
$string['currentbuffersize'] = 'Tamanho atual do buffer: {$a} eventos';
$string['asyncmode_enabled'] = 'Modo assíncrono ativado (tamanho do buffer: {$a->buffersize}, intervalo de descarga: {$a->flushinterval}s)';
$string['syncmode_enabled'] = 'Modo síncrono ativado (gravações imediatas)';
$string['backtosettings'] = 'Voltar para Configurações';

// Error messages.
$string['error_connection'] = 'Falha ao conectar ao servidor TimescaleDB em {$a->host}:{$a->port}';
$string['error_missingconfig'] = 'Configuração obrigatória ausente: {$a}';

$string['serverurl'] = 'URL do servidor TSDB';
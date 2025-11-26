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
  * db/install.php para logstore_timescaledb.
  *
  * - Torna a tabela criada via install.xml um hypertable do TimescaleDB (item obrigatório para séries temporais).
  * - Cria índices em colunas cruciais para otimizar queries de analytics (opcional, mas altamente recomendado).
  * - Ativa compressão, política de retenção e continuous aggregates para grande eficiência de storage e relatórios (opcionais, mas altamente recomendados para bancos grandes).
  * - Cria uma função auxiliar SQL para relatórios rápidos (opcional).
  * 
  * OBS: Este arquivo só é executado automaticamente na instalação do plugin.
  * Para upgrades de schema, use upgrade.php; para limpeza/remoção na desinstalação, use uninstall.php.
  */

function xmldb_logstore_timescaledb_install() {
    global $DB;

    // 1. Transformando em Hypertable (OBRIGATÓRIO com TimescaleDB)
    $DB->execute("
        SELECT create_hypertable('moodle_events', 'time', chunk_time_interval => INTERVAL '1 day', if_not_exists => TRUE);
    ");

    // 2. Criação dos índices (ALTAMENTE RECOMENDADO para performance)
    $DB->execute("CREATE INDEX IF NOT EXISTS idx_moodle_events_time ON moodle_events (time DESC);");
    $DB->execute("CREATE INDEX IF NOT EXISTS idx_moodle_events_eventname ON moodle_events (time DESC, eventname);");
    $DB->execute("CREATE INDEX IF NOT EXISTS idx_moodle_events_userid ON moodle_events (time DESC, userid);");
    $DB->execute("CREATE INDEX IF NOT EXISTS idx_moodle_events_courseid ON moodle_events (time DESC, courseid) WHERE courseid IS NOT NULL;");
    $DB->execute("CREATE INDEX IF NOT EXISTS idx_moodle_events_contextid ON moodle_events (time DESC, contextid);");
    $DB->execute("CREATE INDEX IF NOT EXISTS idx_moodle_events_crud ON moodle_events (crud, time DESC);");
    $DB->execute("CREATE INDEX IF NOT EXISTS idx_moodle_events_edulevel ON moodle_events (edulevel, time DESC);");
    $DB->execute("CREATE INDEX IF NOT EXISTS idx_moodle_events_component_action ON moodle_events (component, action, time DESC);");

    // 3. Configuração de compressão e políticas (OPCIONAL, mas recomendado)
    $DB->execute("ALTER TABLE moodle_events SET (
        timescaledb.compress,
        timescaledb.compress_segmentby = 'component, action, edulevel',
        timescaledb.compress_orderby = 'time DESC, userid'
    );");
    $DB->execute("SELECT add_compression_policy('moodle_events', INTERVAL '7 days');");
    $DB->execute("SELECT add_retention_policy('moodle_events', INTERVAL '1 year');");

    // 4. Continuous aggregates/views de relatórios (OPCIONAL, recomendado para analytics)
    $DB->execute("CREATE MATERIALIZED VIEW IF NOT EXISTS moodle_events_hourly
        WITH (timescaledb.continuous) AS
        SELECT
            time_bucket('1 hour', time) AS bucket,
            component,
            action,
            edulevel,
            COUNT(*) as event_count,
            COUNT(DISTINCT userid) as unique_users,
            COUNT(DISTINCT courseid) as unique_courses
        FROM moodle_events
        WHERE courseid IS NOT NULL
        GROUP BY bucket, component, action, edulevel
        WITH NO DATA;
    ");
    $DB->execute("SELECT add_continuous_aggregate_policy('moodle_events_hourly',
        start_offset => INTERVAL '3 hours',
        end_offset => INTERVAL '1 hour',
        schedule_interval => INTERVAL '1 hour'
    );");

    $DB->execute("CREATE MATERIALIZED VIEW IF NOT EXISTS moodle_events_daily
        WITH (timescaledb.continuous) AS
        SELECT
            time_bucket('1 day', time) AS bucket,
            component,
            COUNT(*) as event_count,
            COUNT(DISTINCT userid) as unique_users,
            COUNT(DISTINCT courseid) as unique_courses
        FROM moodle_events
        WHERE courseid IS NOT NULL
        GROUP BY bucket, component
        WITH NO DATA;
    ");
    $DB->execute("SELECT add_continuous_aggregate_policy('moodle_events_daily',
        start_offset => INTERVAL '7 days',
        end_offset => INTERVAL '1 day',
        schedule_interval => INTERVAL '1 day'
    );");

    // 5. Função auxiliar para relatórios rápidos (OPCIONAL)
    $DB->execute("CREATE OR REPLACE FUNCTION get_events_per_hour(hours INTEGER DEFAULT 24)
RETURNS TABLE (
    hour TIMESTAMPTZ,
    count BIGINT
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        time_bucket('1 hour', time) AS hour,
        COUNT(*) AS count
    FROM moodle_events
    WHERE time > NOW() - (hours || ' hours')::INTERVAL
    GROUP BY hour
    ORDER BY hour DESC;
END;
$$ LANGUAGE plpgsql;");
}

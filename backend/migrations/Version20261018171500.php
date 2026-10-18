<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018171500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cadastral areas, parcels and app state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS postgis');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS unaccent');
        $this->addSql(<<<'SQL'
            CREATE FUNCTION immutable_unaccent(text) RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
            AS $$ SELECT public.unaccent('public.unaccent', $1) $$
            SQL);

        $this->addSql('CREATE SCHEMA staging');

        $this->addSql(<<<'SQL'
            CREATE TABLE cadastral_area (
                code integer PRIMARY KEY,
                name text NOT NULL,
                municipality_code integer,
                municipality_name text,
                status text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'importing', 'done', 'failed')),
                parcel_count integer NOT NULL DEFAULT 0,
                outline geometry(MultiPolygon, 3857),
                error text,
                imported_at timestamptz
            )
            SQL);
        $this->addSql('CREATE INDEX cadastral_area_outline_idx ON cadastral_area USING gist (outline)');
        $this->addSql('CREATE INDEX cadastral_area_name_trgm_idx ON cadastral_area USING gin (immutable_unaccent(lower(name)) gin_trgm_ops)');

        $this->addSql(<<<'SQL'
            CREATE TABLE parcel (
                id bigint PRIMARY KEY,
                area_code integer NOT NULL REFERENCES cadastral_area (code) ON DELETE CASCADE,
                label text NOT NULL,
                reference text,
                area_m2 integer,
                land_type text,
                land_use text,
                geom geometry(MultiPolygon, 3857) NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX parcel_geom_idx ON parcel USING gist (geom)');
        $this->addSql('CREATE INDEX parcel_label_area_idx ON parcel (label text_pattern_ops, area_code)');
        $this->addSql('CREATE INDEX parcel_area_code_idx ON parcel (area_code)');

        $this->addSql(<<<'SQL'
            CREATE TABLE app_state (
                id smallint PRIMARY KEY DEFAULT 1 CHECK (id = 1),
                data_version integer NOT NULL DEFAULT 0
            )
            SQL);
        $this->addSql('INSERT INTO app_state DEFAULT VALUES');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_state');
        $this->addSql('DROP TABLE parcel');
        $this->addSql('DROP TABLE cadastral_area');
        $this->addSql('DROP SCHEMA staging CASCADE');
        $this->addSql('DROP FUNCTION immutable_unaccent(text)');
    }
}

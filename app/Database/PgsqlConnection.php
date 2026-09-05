<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection;
use PDO;

/**
 * Connexion Postgres avec requêtes préparées émulées (nécessaire derrière le pooler Neon).
 *
 * Laravel convertit les booléens en 0/1 avant liaison ; en mode émulé PDO les écrit comme des
 * entiers et Postgres refuse ("operator does not exist: boolean = integer"). On conserve donc
 * les booléens tels quels et on les lie avec PDO::PARAM_BOOL.
 */
class PgsqlConnection extends PostgresConnection
{
    public function prepareBindings(array $bindings)
    {
        $booleans = [];
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $booleans[$key] = $value;
            }
        }

        $prepared = parent::prepareBindings($bindings);

        foreach ($booleans as $key => $value) {
            $prepared[$key] = $value;
        }

        return $prepared;
    }

    public function bindValues($statement, $bindings)
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                match (true) {
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_int($value) => PDO::PARAM_INT,
                    is_resource($value) => PDO::PARAM_LOB,
                    default => PDO::PARAM_STR,
                },
            );
        }
    }
}

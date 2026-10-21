<?php

namespace App\Rules\Audit;

/**
 * Une règle d'audit reçoit les données collectées sur l'instance
 * et renvoie la liste de ses constats.
 *
 * Chaque constat est un tableau : ['object' => ..., 'evidence' => ..., 'recommendation' => ...]
 * La sévérité n'est pas décidée ici : elle vient de la table rules.
 */
interface AuditRule
{
    public function evaluate(array $data): array;
}

<?php

namespace totum\common\Services;

interface ServicesVarsInterface
{
    public function insertName(string $varName, int $expired = null): bool;

    public function setVarValue(string $varName, $value, string $mark = null): void;

    public function getVarValue(string $varName, string $mark = null);

    public function waitVarValue(string $varName, float $timeout = 10);

    public function getNewVarnameHash(int $expired = null): string;


}
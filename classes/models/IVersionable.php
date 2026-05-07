<?php

interface IVersionable
{
    public function saveSnapshot(int $itineraryId, int $changedBy): void;
    public function restoreSnapshot(int $versionId): array;
}

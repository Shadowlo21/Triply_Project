<?php

interface IDocumentService
{
    public function uploadDocument(int $userId, int $tripId, array $file, string $visibility): bool;
    public function deleteDocument(int $docId, int $requesterId): bool;
    public function checkVisaRequirements(string $nationality, string $destination): bool;
    public function verifyAccessRights(int $userId, int $docId): bool;
    public function downloadDocument(int $docId): void;
}

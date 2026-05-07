<?php

class HealthInfoController
{
    public function saveHealthRequest(array $data, int $memberId): void
    {
        $info = HealthInfo::forUser($memberId);
        $info->createOrUpdate($data);
    }

    public function getOwnRecord(int $memberId): array
    {
        return HealthInfo::forUser($memberId)->healthData;
    }

    public function getEmergencyRecord(int $targetUserId, User $requester): array
    {
        if ($requester->getRole() !== 'admin' && !($requester instanceof TripLeader)) {
            return [];
        }
        return HealthInfo::forUser($targetUserId)->healthData;
    }
}

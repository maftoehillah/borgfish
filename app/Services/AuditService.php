<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    /**
     * Create an audit log entry
     *
     * @param string|null $actorType
     * @param int|null $actorId
     * @param string $action
     * @param string|null $resourceType
     * @param int|null $resourceId
     * @param array $payload
     * @return AuditLog
     */
    public static function log(?string $actorType, ?int $actorId, string $action, ?string $resourceType = null, ?int $resourceId = null, array $payload = []): AuditLog
    {
        return AuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'payload' => $payload,
        ]);
    }
}

<?php

return [
    'default_auth_driver' => null,
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,
    'subject_returns_soft_deleted_models' => false,
    'active_by_default' => true,
    'delete_records_older_than_days' => 365,
    'delete_records_older_than_days_schedule' => '0 0 * * *',
    'auth_driver' => null,
    'default_log_name' => 'default',
    'logger' => \Spatie\Activitylog\Logger::class,
    'queue_log_jobs' => false,
];

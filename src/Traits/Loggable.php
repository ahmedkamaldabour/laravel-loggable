<?php

declare(strict_types=1);

namespace Devdabour\LaravelLoggable\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Str;

use function array_keys;
use function is_array;

/**
 * A trait that extends Spatie's LogsActivity trait with additional functionality.
 *
 * @property array $logAttributes Custom mapping of attributes to their human-readable names
 * @property string $logName Custom name for the log
 * @property bool $logOnlyDirty Whether to log only changed attributes
 * @property array $excludeFromLogging Attributes to exclude from logging
 * @property bool $logMetadata Whether to log metadata (IP, user agent)
 */
trait Loggable
{
    use LogsActivity;

    public bool $logOnlyDirty = true;

    /**
     * Get log attributes dynamically or set default.
     *
     * @return array
     */
    public function getLogAttributes(): array
    {
        // Check if 'logAttributes' exists as a static property and is not null
        if (isset(static::$logAttributes) && is_array(static::$logAttributes)) {
            return array_keys(static::$logAttributes);
        }

        // Default to logging all attributes if no mapping is provided
        return ['*'];
    }

    /**
     * Get attributes that should be excluded from logging.
     *
     * @return array
     */
    public function getExcludeFromLogging(): array
    {
        return static::$excludeFromLogging ?? [];
    }

    /**
     * Get log name dynamically or set default.
     *
     * @return string
     */
    public function getLogName(): string
    {
        return static::$logName ?? class_basename($this);
    }

    /**
     * Get Activitylog options dynamically or set default.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getLogAttributes())
            ->useLogName($this->getLogName())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Tap into the activity before it is logged.
     *
     * @param Activity $activity
     * @param string $eventName
     * @return void
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $oldLogAttributes = $activity->properties->get('old', []);
        $logAttributes = $activity->properties->get('attributes', []);

        // Apply exclusions
        $excludedAttributes = $this->getExcludeFromLogging();
        foreach ($excludedAttributes as $excluded) {
            unset($oldLogAttributes[$excluded], $logAttributes[$excluded]);
        }

        // Apply size limits to large text fields to prevent "Content Too Large" errors
        $this->limitLargeAttributes($oldLogAttributes);
        $this->limitLargeAttributes($logAttributes);

        $this->customTapActivity($activity, $oldLogAttributes, $logAttributes);

        $mappedOldLogAttributes = $this->mapLogAttributes($oldLogAttributes);
        $mappedLogAttributes = $this->mapLogAttributes($logAttributes);

        if (empty($mappedOldLogAttributes) && empty($mappedLogAttributes)) {
            $activity->delete();

            return;
        }

        $activity->properties = $activity->properties->merge([
            'old' => $mappedOldLogAttributes,
            'attributes' => $mappedLogAttributes,
        ]);

        // Add metadata
        $metadata = [];

        // Add device info if enabled
        if ($this->shouldLogMetadata()) {
            $metadata['device_info'] = [
                'user_agent' => request()?->userAgent(),
                'ip_address' => request()?->ip(),
            ];
        }

        // Add additional context data
        $additionalData = $this->getAdditionalLogData();
        if (!empty($additionalData)) {
            $metadata['context'] = $additionalData;
        }

        // Add related models data
        $relationshipsData = $this->getRelationshipsLogData();
        if (!empty($relationshipsData)) {
            $metadata['related'] = $relationshipsData;
        }

        // Merge metadata into properties if not empty
        if (!empty($metadata)) {
            $activity->properties = $activity->properties->merge(['metadata' => $metadata]);
        }
    }

    /**
     * Limit the size of large text attributes to prevent issues with broadcasting
     * and storage limitations.
     *
     * @param array &$attributes
     * @return void
     */
    protected function limitLargeAttributes(array &$attributes): void
    {
        $maxLength = 1000; // Adjust this based on your needs

        foreach ($attributes as $key => $value) {
            if (is_string($value) && Str::length($value) > $maxLength) {
                $attributes[$key] = Str::limit($value, $maxLength);
            } elseif (is_array($value) || is_object($value)) {
                // For arrays and objects, convert to JSON and limit the size
                $json = json_encode($value);
                if (Str::length($json) > $maxLength) {
                    $attributes[$key] = '[Content too large - truncated]';
                }
            }
        }
    }

    /**
     * Determine if additional metadata should be added to logs.
     *
     * @return bool
     */
    public function shouldLogMetadata(): bool
    {
        return static::$logMetadata ?? false;
    }

    /**
     * Custom description for logged events.
     *
     * @param string $eventName
     * @return string
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => "Created new {$this->getLogName()}",
            'updated' => "Updated {$this->getLogName()}",
            'deleted' => "Deleted {$this->getLogName()}",
            'restored' => "Restored {$this->getLogName()}",
            default => "This {$this->getLogName()} has been {$eventName}",
        };
    }

    /**
     * Map the log attributes to a custom key format.
     *
     * @param array $attributes
     * @return array
     */
    protected function mapLogAttributes(array $attributes): array
    {
        $mapping = static::$logAttributes ?? [];
        $result = [];

        // Check if we need to handle translatable fields
        $hasTranslatable = property_exists($this, 'translatable') && is_array($this->translatable);

        // Check if we have JSON fields configuration
        $jsonFields = static::$jsonFields ?? [];

        foreach ($attributes as $key => $value) {
            // Check if this attribute has a nested mapping in $logAttributes
            $hasNestedMapping = isset($mapping[$key]) && is_array($mapping[$key]) && !isset($mapping[$key][0]);

            // Determine if this is a JSON field (either a translatable field or explicitly defined as JSON)
            $isJsonField = ($hasTranslatable && in_array($key, $this->translatable)) ||
                           in_array($key, $jsonFields) ||
                           $hasNestedMapping;

            // Process JSON fields
            if ($isJsonField) {
                // Convert to array if it's a JSON string
                $jsonData = $value;
                if (is_string($value) && $this->isJson($value)) {
                    $jsonData = json_decode($value, true);
                }

                if (is_array($jsonData)) {
                    // If it's a nested mapping (language-specific or JSON structure)
                    if ($hasNestedMapping) {
                        foreach ($jsonData as $nestedKey => $nestedValue) {
                            if (isset($mapping[$key][$nestedKey])) {
                                // Use the defined label for this nested key
                                $label = $mapping[$key][$nestedKey];
                                $result[$label] = $nestedValue;
                            } else {
                                // Use a default format for keys not explicitly mapped
                                $label = ($mapping[$key] ?? $key) . " ({$nestedKey})";
                                $result[$label] = $nestedValue;
                            }
                        }
                    } else {
                        // For JSON fields without specific nested mappings
                        // Format each key-value pair using dot notation or a similar approach
                        $this->flattenJsonArray($jsonData, $result, $key, $mapping);
                    }
                    continue; // Skip the default mapping below
                }
            }

            // Standard mapping for non-JSON fields
            $label = $mapping[$key] ?? $key;

            // Only use the label if it's a string (not an array)
            if (!is_array($label)) {
                $result[$label] = $value;
            } else {
                // If the label is an array but the value isn't JSON data,
                // just use the field name as the label
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Recursively flatten a JSON array into dot notation.
     *
     * @param array $array The array to flatten
     * @param array &$result The result array to append to
     * @param string $prefix The prefix for keys
     * @param array $mapping Attribute mappings
     * @param int $depth Current recursion depth
     * @return void
     */
    protected function flattenJsonArray(array $array, array &$result, string $prefix, array $mapping, int $depth = 0): void
    {
        // Prevent too deep recursion
        if ($depth > 3) {
            $result[$prefix] = json_encode($array);
            return;
        }

        foreach ($array as $key => $value) {
            $newKey = $prefix . '.' . $key;

            // Check if we have a mapping for this specific JSON path
            $mappedKey = $mapping[$newKey] ?? null;

            if (is_array($value) && !empty($value) && $depth < 3) {
                // Recursively process nested arrays
                $this->flattenJsonArray($value, $result, $newKey, $mapping, $depth + 1);
            } else {
                // For non-array values or at max depth, add to results
                $displayKey = $mappedKey ?? $newKey;
                $result[$displayKey] = $value;
            }
        }
    }

    /**
     * Custom tapActivity method to be overridden in models.
     *
     * @param Activity $activity
     * @param array &$oldLogAttributes
     * @param array &$logAttributes
     * @return void
     */
    protected function customTapActivity(Activity $activity, array &$oldLogAttributes, array &$logAttributes): void
    {
        if (method_exists($this, 'modelCustomTapActivity')) {
            $this->modelCustomTapActivity($activity, $oldLogAttributes, $logAttributes);
        }
    }

    /**
     * Get additional context data for logging.
     *
     * @return array
     */
    protected function getAdditionalLogData(): array
    {
        $data = [];

        // Check if model defines additional data to log
        if (isset(static::$logAdditionalData) && is_array(static::$logAdditionalData)) {
            foreach (static::$logAdditionalData as $field => $label) {
                // Check for accessor method first
                $accessorMethod = 'get' . Str::studly($field) . 'Attribute';
                if (method_exists($this, $accessorMethod)) {
                    $data[$label] = $this->{$accessorMethod}();
                } elseif (property_exists($this, $field) || isset($this->attributes[$field])) {
                    $data[$label] = $this->{$field};
                }
            }
        }

        return $data;
    }

    /**
     * Get related models data for logging.
     *
     * @return array
     */
    protected function getRelationshipsLogData(): array
    {
        $data = [];

        // Check if model defines relationships to include in logs
        if (isset(static::$logRelationships) && is_array(static::$logRelationships)) {
            foreach (static::$logRelationships as $relation => $fields) {
                // Check if the relationship is loaded
                if ($this->relationLoaded($relation) && $this->{$relation}) {
                    $relatedData = [];

                    foreach ($fields as $field) {
                        if (property_exists($this->{$relation}, $field) ||
                            isset($this->{$relation}->attributes[$field])) {
                            $relatedData[$field] = $this->{$relation}->{$field};
                        }
                    }

                    if (!empty($relatedData)) {
                        $data[$relation] = $relatedData;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Custom CauserResolver to handle null users in testing environments.
     *
     * This method will be used by the Spatie activity log package to determine
     * who caused the action. We're modifying it to handle null users gracefully.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getCauser()
    {
        if (auth()->guest()) {
            // Return null or a system user if no user is authenticated
            return null;
        }

        return auth()->user();
    }

    /**
     * Check if a string is valid JSON.
     *
     * This method is made public to be usable outside the trait context.
     *
     * @param string $string
     * @return bool
     */
    public function isJson($string): bool
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}

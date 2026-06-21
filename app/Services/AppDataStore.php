<?php

namespace App\Services;

use App\Models\AppData;
use App\Support\DataKeys;
use Illuminate\Support\Facades\Cache;

class AppDataStore
{
    public function read(string $key): array
    {
        if (! DataKeys::allowed($key)) {
            return [];
        }

        $row = AppData::query()
            ->where('data_key', $key)
            ->first();

        if (! $row) {
            return $this->createDefaultRow($key);
        }

        return is_array($row->data_value)
            ? $row->data_value
            : DataKeys::defaultValue($key);
    }

    public function write(string $key, mixed $value, ?string $updatedBy = null): array
    {
        if (! DataKeys::allowed($key)) {
            throw new \InvalidArgumentException("Data key tidak dikenal: {$key}");
        }

        $cleanValue = is_array($value) ? $value : DataKeys::defaultValue($key);

        AppData::query()->updateOrCreate(
            ['data_key' => $key],
            [
                'data_value' => $cleanValue,
                'updated_by' => $updatedBy,
                'updated_at' => now(),
            ]
        );

        Cache::forget('akta_all_data');

        return $cleanValue;
    }

    public function all(): array
    {
        return Cache::remember(
            'akta_all_data',
            (int) config('akta.data_cache_ttl', 60),
            function () {
                $result = [];

                foreach (DataKeys::all() as $key) {
                    $result[$key] = $this->read($key);
                }

                return $result;
            }
        );
    }

    public function forgetCache(): void
    {
        Cache::forget('akta_all_data');
    }

    private function createDefaultRow(string $key): array
    {
        $defaultValue = DataKeys::defaultValue($key);

        AppData::query()->create([
            'data_key' => $key,
            'data_value' => $defaultValue,
            'updated_by' => 'system',
            'updated_at' => now(),
        ]);

        return $defaultValue;
    }
}

<?php

namespace App\Services;

class AktaMenuService
{
    public function __construct(
        private readonly AppDataStore $store
    ) {}

    public function items(): array
    {
        $defaults = config('akta_menu.items', []);
        $stored = $this->store->read('akta_menu_config');

        $overrides = $stored['items'] ?? [];

        $items = collect($defaults)
            ->map(function (array $item, int $index) use ($overrides) {
                $key = $item['route'];
                $override = $overrides[$key] ?? [];

                return [
                    'label' => $override['label'] ?? $item['label'],
                    'route' => $item['route'],
                    'path' => $item['path'],
                    'code' => $override['code'] ?? $item['code'],
                    'admin_only' => (bool) ($override['admin_only'] ?? $item['admin_only']),
                    'visible' => (bool) ($override['visible'] ?? true),
                    'order' => (int) ($override['order'] ?? ($index + 1)),
                ];
            })
            ->sortBy('order')
            ->values()
            ->all();

        return $items;
    }

    public function visibleItems(): array
    {
        return collect($this->items())
            ->where('visible', true)
            ->values()
            ->all();
    }

    public function update(array $items, ?string $updatedBy = null): array
    {
        $defaults = collect(config('akta_menu.items', []))
            ->keyBy('route');

        $payload = [
            'items' => [],
            'updated_by' => $updatedBy,
            'updated_at' => now()->toDateTimeString(),
        ];

        foreach ($items as $index => $item) {
            $route = $item['route'] ?? null;

            if (! $route || ! $defaults->has($route)) {
                continue;
            }

            $default = $defaults->get($route);

            $payload['items'][$route] = [
                'label' => trim((string) ($item['label'] ?? $default['label'])),
                'code' => strtoupper(substr(trim((string) ($item['code'] ?? $default['code'])), 0, 3)),
                'admin_only' => (bool) ($item['admin_only'] ?? $default['admin_only']),
                'visible' => (bool) ($item['visible'] ?? true),
                'order' => (int) ($item['order'] ?? ($index + 1)),
            ];
        }

        $this->store->write('akta_menu_config', $payload, $updatedBy);

        return $this->items();
    }

    public function reset(?string $updatedBy = null): array
    {
        $this->store->write('akta_menu_config', [
            'items' => [],
            'updated_by' => $updatedBy,
            'updated_at' => now()->toDateTimeString(),
        ], $updatedBy);

        return $this->items();
    }
}

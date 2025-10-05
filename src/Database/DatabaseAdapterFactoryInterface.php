<?php

namespace Flux\Database;

interface DatabaseAdapterFactoryInterface
{
    /**
     * Get the appropriate database adapter for the current connection
     */
    public function create(?string $connection = null): DatabaseAdapter;

    /**
     * Clear any cached adapter instances
     */
    public function clearCache(): void;

    /**
     * Register a custom adapter for a driver
     */
    public function registerAdapter(string $driver, string $adapterClass): void;
}

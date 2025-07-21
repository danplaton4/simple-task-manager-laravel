<?php

namespace App\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Base Data Transfer Object providing common functionality
 */
abstract class BaseDTO implements Arrayable, JsonSerializable
{
    /**
     * Convert the DTO to an array
     */
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        $data = [];
        foreach ($properties as $property) {
            $value = $property->getValue($this);
            
            // Handle nested DTOs
            if ($value instanceof BaseDTO) {
                $data[$property->getName()] = $value->toArray();
            } elseif (is_array($value)) {
                $data[$property->getName()] = array_map(function ($item) {
                    return $item instanceof BaseDTO ? $item->toArray() : $item;
                }, $value);
            } else {
                $data[$property->getName()] = $value;
            }
        }
        
        return $data;
    }

    /**
     * Convert the DTO to JSON serializable format
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Create DTO from array data
     */
    public static function fromArray(array $data): static
    {
        $reflection = new \ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return new static();
        }
        
        $parameters = $constructor->getParameters();
        $args = [];
        
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $value = $data[$name] ?? null;
            
            // Handle default values for optional parameters
            if ($value === null && $parameter->isDefaultValueAvailable()) {
                $value = $parameter->getDefaultValue();
            }
            
            $args[] = $value;
        }
        
        return new static(...$args);
    }
}
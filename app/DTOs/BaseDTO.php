<?php

namespace App\DTOs;

abstract class BaseDTO
{
    /**
     * Convert the DTO to an array.
     */
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        $result = [];
        foreach ($properties as $property) {
            $value = $property->getValue($this);
            
            // Handle nested DTOs
            if ($value instanceof BaseDTO) {
                $result[$property->getName()] = $value->toArray();
            } elseif (is_array($value)) {
                $result[$property->getName()] = array_map(function ($item) {
                    return $item instanceof BaseDTO ? $item->toArray() : $item;
                }, $value);
            } else {
                $result[$property->getName()] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Convert the DTO to JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
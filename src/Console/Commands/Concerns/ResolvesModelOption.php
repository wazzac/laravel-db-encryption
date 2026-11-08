<?php

namespace Wazza\DbEncrypt\Console\Commands\Concerns;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

trait ResolvesModelOption
{
    /**
     * Resolve the --model option to a model instance.
     *
     * @return Model|null Null when the option wasn't given.
     * @throws InvalidArgumentException When the option doesn't name an Eloquent model.
     */
    protected function resolveModelOption(): ?Model
    {
        $class = $this->option('model');

        if (!$class) {
            return null;
        }

        if (!is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException("'{$class}' is not an Eloquent model class.");
        }

        return new $class();
    }
}

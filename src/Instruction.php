<?php

namespace ExtractrIo\Rialto;

use BadMethodCallException;
use InvalidArgumentException;
use ExtractrIo\Rialto\Interfaces\ShouldIdentifyResource;
use ExtractrIo\Rialto\Exceptions\Node\Exception as NodeException;

class Instruction implements \JsonSerializable
{
    use Data\SerializesData;

    public const TYPE_CALL = 'call';
    public const TYPE_GET = 'get';
    public const TYPE_SET = 'set';

    /**
     * The instruction type.
     *
     * @var string
     */
    protected $type;

    /**
     * The name the instruction refers to.
     *
     * @var string
     */
    protected $name;

    /**
     * The value(s) the instruction should use.
     *
     * @var array[]|array|null
     */
    protected $value;

    /**
     * The identifier of the resource associated to the instruction.
     *
     * @var string|null
     */
    protected $resourceId;

    /**
     * Define whether instruction errors should be catched.
     *
     * @var boolean
     */
    protected $shouldCatchErrors = false;

    /**
     * Define a method call.
     */
    public function call(string $name, ...$arguments): self
    {
        $this->type = self::TYPE_CALL;
        $this->name = $name;
        $this->setValue($arguments, $this->type);

        return $this;
    }

    /**
     * Define a getter.
     */
    public function get(string $name): self
    {
        $this->type = self::TYPE_GET;
        $this->name = $name;
        $this->setValue(null, $this->type);

        return $this;
    }

    /**
     * Define a setter.
     */
    public function set(string $name, $value): self
    {
        $this->type = self::TYPE_SET;
        $this->name = $name;
        $this->setValue($value, $this->type);

        return $this;
    }

    /**
     * Link the instruction to the provided resource.
     */
    public function linkToResource(?ShouldIdentifyResource $resource): self
    {
        $this->resourceId = $resource !== null ? $resource->getResourceIdentity()->uniqueIdentifier() : null;

        return $this;
    }

    /**
     * Define if instruction errors should be catched.
     */
    public function shouldCatchErrors(bool $catch): self
    {
        $this->shouldCatchErrors = $catch;

        return $this;
    }

    /**
     * Set the instruction value.
     */
    protected function setValue($value, string $type)
    {
        $this->value = $type !== self::TYPE_CALL
            ? $this->validateValue($value)
            : array_map(function ($value) {
                return $this->validateValue($value);
            }, $value);
    }

    /**
     * Validate a value.
     *
     * @throws \InvalidArgumentException if the value contains PHP closures.
     */
    protected function validateValue($value)
    {
        if (is_object($value) && ($value instanceof Closure)) {
            throw new InvalidArgumentException('You must use JS function wrappers instead of PHP closures.');
        }

        return $value;
    }

    /**
     * Serialize the object to a value that can be serialized natively by {@see json_encode}.
     */
    public function jsonSerialize(): array
    {
        $instruction = [
            'type' => $this->type,
            'name' => $this->name,
            'catched' => $this->shouldCatchErrors,
        ];

        if ($this->type !== self::TYPE_GET) {
            $instruction['value'] = $this->type === self::TYPE_SET
                ? $this->serialize($this->value)
                : array_map(function ($value) {
                    return $this->serialize($value);
                }, $this->value);
        }

        if ($this->resourceId !== null) {
            $instruction['resource'] = $this->resourceId;
        }

        return $instruction;
    }

    /**
     * Proxy the "with*" static method calls to the "*" non-static method calls of a new instance.
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $name = lcfirst(substr($name, strlen('with')));

        if ($name === 'jsonSerialize') {
            throw new BadMethodCallException;
        }

        return call_user_func([new self, $name], ...$arguments);
    }
}

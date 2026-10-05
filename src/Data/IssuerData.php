<?php

namespace Laragear\Dte\Data;

use ArrayAccess;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Laragear\Dte\Validation\DteRules;
use Laragear\Rut\Rut;
use LogicException;

use function is_array;
use function json_decode;
use function json_encode;
use function preg_match;
use function validator;

/**
 * @implements ArrayAccess<string, mixed>
 */
readonly class IssuerData implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    /**
     * Create a new Issuer Data instance.
     */
    public function __construct(
        public Rut $rut,
        public string $name,
        public string $activity,
        public string|array $activityCode,
        public string $address,
        public string $commune,
        public string $resolutionDate,
        public int $resolutionNumber,
        public ?string $city = null,
        public ?string $telephone = null,
        public ?string $email = null,
        public ?string $branch = null,
    ) {
        //
    }

    /**
     * Create a new instance fluently
     */
    public static function make(
        Rut|string $rut,
        string $name,
        string $activity,
        string|array $activityCode,
        string $address,
        string $commune,
        string $resolutionDate,
        int $resolutionNumber = 0,
        ?string $city = null,
        ?string $telephone = null,
        ?string $email = null,
        ?string $branch = null,
    ): static {
        return new static(
            Rut::parse($rut),
            $name,
            $activity,
            $activityCode,
            $address,
            $commune,
            $resolutionDate,
            $resolutionNumber,
            $city,
            $telephone,
            $email,
            $branch
        );
    }

    /**
     * Create a new instance from an array.
     */
    public static function fromArray(array $array): static
    {
        return static::make(
            $array['rut'],
            $array['name'],
            $array['activity'],
            $array['activity_code'],
            $array['address'],
            $array['commune'],
            $array['resolution_date'],
            $array['resolution_number'] ?? 0,
            $array['city'] ?? null,
            $array['telephone'] ?? null,
            $array['email'] ?? null,
            $array['branch'] ?? null,
        );
    }

    /**
     * Create an instance from a JSON string.
     */
    public static function fromJson(string $json): static
    {
        return static::fromArray(json_decode($json, true, 5));
    }

    /**
     * Return the validation rules for the issuer data.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $rules = DteRules::ISSUER;

        // The economic activity may be a single code or a list of up to 4 codes of up to 6 digits.
        $rules['activity_code'] = static function (string $attribute, mixed $value, Closure $fail): void {
            $codes = is_array($value) ? $value : [$value];

            if (count($codes) > 4) {
                $fail('The issuer may declare up to 4 economic activity codes.');

                return;
            }

            foreach ($codes as $code) {
                if (! preg_match('/^[0-9]{1,6}$/', (string) $code) || (int) $code < 1) {
                    $fail('The economic activity code must be a positive integer of up to 6 digits.');
                }
            }
        };

        return $rules;
    }

    /**
     * Validate the issuer data against the SII XSD rules.
     */
    public function validate(): void
    {
        validator($this->toArray(), static::rules())->validate();
    }

    /**
     * Convert the instance to an array.
     *
     * @return array{rut: string, name: string, activity: string, activity_code: string|array, address: string, commune: string, resolution_date: string, resolution_number: int, city: string|null, telephone: string|null, email: string|null, branch: string|null}
     */
    public function toArray(): array
    {
        return [
            'rut' => $this->rut->formatRaw(),
            'name' => $this->name,
            'activity' => $this->activity,
            'activity_code' => $this->activityCode,
            'address' => $this->address,
            'commune' => $this->commune,
            'resolution_date' => $this->resolutionDate,
            'resolution_number' => $this->resolutionNumber,
            'city' => $this->city,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'branch' => $this->branch,
        ];
    }

    /**
     * Convert the instance to JSON.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{rut: string, name: string, activity: string, activity_code: string|array, address: string, commune: string, resolution_date: string, resolution_number: int, city: string|null, telephone: string|null, email: string|null, branch: string|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->toArray()[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('IssuerData is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('IssuerData is read-only.');
    }
}

<?php

namespace App\Domain\Shared\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
}

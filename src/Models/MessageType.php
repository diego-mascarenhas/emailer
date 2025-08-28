<?php

namespace idoneo\Emailer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'emailer_message_types';

    protected $fillable = ['name', 'status'];

    /**
     * Get all message types as options array
     */
    public static function getOptions()
    {
        return self::all()->map(function ($data) {
            return [
                'id' => $data->id,
                'name' => $data->name,
            ];
        });
    }

    /**
     * Get active message types
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Get messages of this type
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'type_id');
    }
}

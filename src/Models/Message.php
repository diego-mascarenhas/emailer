<?php

namespace idoneo\Emailer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
	use HasFactory;
	use SoftDeletes;

	public $timestamps = true;

	protected $table = 'emailer_messages';

	protected $fillable = ['name', 'content', 'subject', 'type_id', 'category_id', 'template_id', 'text', 'status_id', 'team_id'];

	protected $casts = [
		'status_id' => 'boolean',
	];

	protected static function booted()
	{
		static::addGlobalScope('team', function (Builder $builder)
		{
			if (auth()->check() && method_exists(auth()->user(), 'currentTeam'))
			{
				$builder->where('team_id', auth()->user()->currentTeam->id);
			}
		});

		static::creating(function ($model)
		{
			if (! $model->team_id && auth()->check() && method_exists(auth()->user(), 'currentTeam'))
			{
				$model->team_id = auth()->user()->currentTeam->id;
			}
		});
	}

	/**
	 * Get the team that owns the message
	 * This relationship assumes the existence of a Team model
	 */
	public function team()
	{
		$teamModel = config('emailer.team_model', 'App\Models\Team');
		return $this->belongsTo($teamModel);
	}

	/**
	 * Get the message type
	 */
	public function type()
	{
		return $this->belongsTo(MessageType::class);
	}

	/**
	 * Get the category
	 * This relationship assumes the existence of a Category model
	 */
	public function category()
	{
		$categoryModel = config('emailer.category_model', 'App\Models\Category');
		return $this->belongsTo($categoryModel);
	}

	/**
	 * Get the template
	 * This relationship assumes the existence of a Template model
	 */
	public function template()
	{
		$templateModel = config('emailer.template_model', 'App\Models\Template');
		return $this->belongsTo($templateModel);
	}

	/**
	 * Get the message deliveries
	 */
	public function deliveries()
	{
		return $this->hasMany(MessageDelivery::class);
	}

	/**
	 * Get pending deliveries count
	 */
	public function getPendingDeliveriesCountAttribute()
	{
		return $this->deliveries()->where('status_id', 0)->count();
	}

	/**
	 * Get sent deliveries count
	 */
	public function getSentDeliveriesCountAttribute()
	{
		return $this->deliveries()->where('status_id', '>', 0)->count();
	}
}

<?php

namespace idoneo\Emailer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDeliveryTracking extends Model
{
	use HasFactory;

	protected $table = 'emailer_message_delivery_tracking';

	protected $fillable = [
		'message_delivery_id',
		'event',
		'tracked_at',
		'ip_address',
		'user_agent',
		'country',
		'city',
		'metadata',
	];

	protected $casts = [
		'tracked_at' => 'datetime',
		'metadata' => 'array',
	];

	/**
	 * Get the message delivery that this tracking belongs to
	 */
	public function delivery()
	{
		return $this->belongsTo(MessageDelivery::class, 'message_delivery_id');
	}

	/**
	 * Scope for opened events
	 */
	public function scopeOpened($query)
	{
		return $query->where('event', 'opened');
	}

	/**
	 * Scope for clicked events
	 */
	public function scopeClicked($query)
	{
		return $query->where('event', 'clicked');
	}

	/**
	 * Scope for delivered events
	 */
	public function scopeDelivered($query)
	{
		return $query->where('event', 'delivered');
	}

	/**
	 * Scope for bounced events
	 */
	public function scopeBounced($query)
	{
		return $query->where('event', 'bounced');
	}

	/**
	 * Create a tracking event
	 */
	public static function createEvent($messageDeliveryId, $eventType = 'opened', $metadata = [])
	{
		$request = request();

		return self::create([
			'message_delivery_id' => $messageDeliveryId,
			'event' => $eventType,
			'tracked_at' => now(),
			'ip_address' => $request ? $request->ip() : null,
			'user_agent' => $request ? $request->userAgent() : null,
			'metadata' => $metadata,
		]);
	}

	/**
	 * Get location information if available
	 */
	public function getLocationAttribute()
	{
		if ($this->city && $this->country) {
			return $this->city . ', ' . $this->country;
		} elseif ($this->country) {
			return $this->country;
		}
		return 'Unknown';
	}

	/**
	 * Get event icon for UI
	 */
	public function getEventIconAttribute()
	{
		switch ($this->event) {
			case 'opened':
				return 'ti ti-eye';
			case 'clicked':
				return 'ti ti-click';
			case 'delivered':
				return 'ti ti-check';
			case 'bounced':
				return 'ti ti-x';
			default:
				return 'ti ti-info-circle';
		}
	}

	/**
	 * Get event color for UI
	 */
	public function getEventColorAttribute()
	{
		switch ($this->event) {
			case 'opened':
				return 'info';
			case 'clicked':
				return 'primary';
			case 'delivered':
				return 'success';
			case 'bounced':
				return 'danger';
			default:
				return 'secondary';
		}
	}
}

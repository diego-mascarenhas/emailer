<?php

namespace idoneo\Emailer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDeliveryLink extends Model
{
	use HasFactory;

	public $timestamps = true;

	protected $table = 'emailer_message_delivery_links';

	protected $fillable = [
		'message_delivery_id',
		'link',
		'original_url',
		'click_count',
		'first_clicked_at',
		'last_clicked_at',
	];

	protected $casts = [
		'created_at' => 'datetime',
		'first_clicked_at' => 'datetime',
		'last_clicked_at' => 'datetime',
	];

	/**
	 * Get the message delivery that this link belongs to
	 */
	public function messageDelivery()
	{
		return $this->belongsTo(MessageDelivery::class, 'message_delivery_id');
	}

	/**
	 * Get the delivery (alias for messageDelivery)
	 */
	public function delivery()
	{
		return $this->messageDelivery();
	}

	/**
	 * Increment click count and update timestamps
	 */
	public function recordClick()
	{
		$this->increment('click_count');

		if (!$this->first_clicked_at) {
			$this->first_clicked_at = now();
		}

		$this->last_clicked_at = now();
		$this->save();
	}

	/**
	 * Check if this link has been clicked
	 */
	public function hasBeenClicked()
	{
		return $this->click_count > 0;
	}

	/**
	 * Get domain from the original URL
	 */
	public function getDomainAttribute()
	{
		if ($this->original_url) {
			$parsedUrl = parse_url($this->original_url);
			return $parsedUrl['host'] ?? 'Unknown';
		}
		return 'Unknown';
	}
}

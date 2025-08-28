<?php

namespace idoneo\Emailer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDelivery extends Model
{
	use HasFactory;

	protected $table = 'emailer_message_deliveries';

	protected $fillable = [
		'team_id',
		'message_id',
		'contact_id',
		'smtp_id',
		'sent_at',
		'delivered_at',
		'removed_at',
		'status_id',
		'email_provider',
		'provider_message_id',
		'delivery_status',
		'bounced_at',
		'opened_at',
		'clicked_at',
		'provider_data',
		'recipient_email',
		'recipient_name',
	];

	protected $casts = [
		'sent_at' => 'datetime',
		'delivered_at' => 'datetime',
		'removed_at' => 'datetime',
		'bounced_at' => 'datetime',
		'opened_at' => 'datetime',
		'clicked_at' => 'datetime',
		'provider_data' => 'array',
	];

	/**
	 * Get the team that owns the delivery
	 */
	public function team()
	{
		$teamModel = config('emailer.team_model', 'App\Models\Team');
		return $this->belongsTo($teamModel);
	}

	/**
	 * Get the message
	 */
	public function message()
	{
		return $this->belongsTo(Message::class);
	}

	/**
	 * Get the contact
	 */
	public function contact()
	{
		$contactModel = config('emailer.contact_model', 'App\Models\Contact');
		return $this->belongsTo($contactModel);
	}

	/**
	 * Get delivery links
	 */
	public function links()
	{
		return $this->hasMany(MessageDeliveryLink::class, 'message_delivery_id');
	}

	/**
	 * Tracking events for this delivery
	 */
	public function trackingEvents()
	{
		return $this->hasMany(MessageDeliveryTracking::class, 'message_delivery_id');
	}

	/**
	 * Generate a tracking token for this delivery
	 */
	public function getTrackingToken()
	{
		return hash('sha256', config('app.key').$this->id);
	}

	/**
	 * Get the tracking URL for open events
	 */
	public function getTrackingUrl()
	{
		return route('emailer.track', ['token' => $this->getTrackingToken()]);
	}

	/**
	 * Get a tracked URL for click events
	 */
	public function getTrackedUrl($originalUrl)
	{
		return route('emailer.track.click', ['token' => $this->getTrackingToken()]).'?url='.urlencode($originalUrl);
	}

	/**
	 * Mark as sent
	 */
	public function markAsSent()
	{
		$this->update([
			'sent_at' => now(),
			'status_id' => 1, // 1 = sent
		]);
	}

	/**
	 * Mark as delivered
	 */
	public function markAsDelivered()
	{
		$this->update([
			'delivered_at' => now(),
			'status_id' => 2, // 2 = delivered
		]);
	}

	/**
	 * Mark as opened
	 */
	public function markAsOpened()
	{
		if (! $this->opened_at)
		{
			$this->update([
				'opened_at' => now(),
				'status_id' => 3, // 3 = opened
			]);
		}
	}

	/**
	 * Mark as clicked
	 */
	public function markAsClicked()
	{
		if (! $this->clicked_at)
		{
			$this->update([
				'clicked_at' => now(),
				'status_id' => 4, // 4 = clicked
			]);
		}
	}

	/**
	 * Mark as error
	 */
	public function markAsError()
	{
		if (! $this->sent_at)
		{
			$this->sent_at = now();
		}
		$this->status_id = 5; // 5 = error
		$this->save();
	}

	/**
	 * Status badge for UI
	 */
	public function getStatusBadgeAttribute()
	{
		switch ($this->status_id) {
			case 4:
				return '<span class="badge bg-primary">Clicked</span>';
			case 3:
				return '<span class="badge bg-info">Opened</span>';
			case 2:
				return '<span class="badge bg-success">Delivered</span>';
			case 1:
				return '<span class="badge bg-success">Sent</span>';
			case 5:
				return '<span class="badge bg-danger">Error</span>';
			default:
				return '<span class="badge bg-warning">Pending</span>';
		}
	}

	/**
	 * Generate personalized HTML for the contact
	 */
	public function getHtmlForContact()
	{
		$templateHtml = $this->message && $this->message->template && isset($this->message->template->gjs_data['html'])
			? $this->message->template->gjs_data['html']
			: ($this->message->content ?? '');

		$contactName = $this->contact ? $this->contact->name : $this->recipient_name;

		// Simple variable replacement for {{name}}
		$html = str_replace('{{name}}', $contactName, $templateHtml);

		// Add tracking image
		$trackingImg = '<img src="'.$this->getTrackingUrl().'" width="1" height="1" style="display:none;" alt="" />';

		if (stripos($html, '</body>') !== false)
		{
			$html = str_ireplace('</body>', $trackingImg.'</body>', $html);
		} else
		{
			$html .= $trackingImg;
		}

		return $html;
	}

	/**
	 * Scope for pending deliveries
	 */
	public function scopePending($query)
	{
		return $query->where('status_id', 0);
	}

	/**
	 * Scope for sent deliveries
	 */
	public function scopeSent($query)
	{
		return $query->where('status_id', '>', 0);
	}

	/**
	 * Scope for opened deliveries
	 */
	public function scopeOpened($query)
	{
		return $query->whereNotNull('opened_at');
	}

	/**
	 * Scope for clicked deliveries
	 */
	public function scopeClicked($query)
	{
		return $query->whereNotNull('clicked_at');
	}
}

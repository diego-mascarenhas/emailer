<?php

namespace idoneo\Emailer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDeliveryStat extends Model
{
	use HasFactory;

	protected $table = 'emailer_message_delivery_stats';

	protected $fillable = [
		'message_id',
		'subscribers',
		'remaining',
		'failed',
		'sent',
		'rejected',
		'delivered',
		'opened',
		'unsubscribed',
		'clicks',
		'unique_opens',
		'ratio',
		'total_contacts',
		'pending_deliveries',
		'success_rate',
		'open_rate',
		'click_rate',
		'bounce_rate',
	];

	protected $casts = [
		'ratio' => 'float',
		'success_rate' => 'float',
		'open_rate' => 'float',
		'click_rate' => 'float',
		'bounce_rate' => 'float',
	];

	/**
	 * Get the message that these stats belong to
	 */
	public function message()
	{
		return $this->belongsTo(Message::class);
	}

	/**
	 * Calculate and update all stats for a message
	 */
	public static function updateForMessage($messageId)
	{
		$message = Message::find($messageId);
		if (!$message) {
			return null;
		}

		$deliveries = $message->deliveries();
		$totalCount = $deliveries->count();
		$sentCount = $deliveries->sent()->count();
		$openedCount = $deliveries->opened()->count();
		$clickedCount = $deliveries->clicked()->count();
		$pendingCount = $deliveries->pending()->count();
		$errorCount = $deliveries->where('status_id', 5)->count();

		$successRate = $totalCount > 0 ? ($sentCount / $totalCount) * 100 : 0;
		$openRate = $sentCount > 0 ? ($openedCount / $sentCount) * 100 : 0;
		$clickRate = $sentCount > 0 ? ($clickedCount / $sentCount) * 100 : 0;
		$bounceRate = $totalCount > 0 ? ($errorCount / $totalCount) * 100 : 0;

		return self::updateOrCreate(
			['message_id' => $messageId],
			[
				'total_contacts' => $totalCount,
				'subscribers' => $totalCount,
				'remaining' => $pendingCount,
				'pending_deliveries' => $pendingCount,
				'sent' => $sentCount,
				'delivered' => $sentCount,
				'opened' => $openedCount,
				'clicks' => $clickedCount,
				'failed' => $errorCount,
				'success_rate' => round($successRate, 2),
				'open_rate' => round($openRate, 2),
				'click_rate' => round($clickRate, 2),
				'bounce_rate' => round($bounceRate, 2),
				'ratio' => round($openRate, 2), // Legacy compatibility
			]
		);
	}

	/**
	 * Get formatted success rate
	 */
	public function getFormattedSuccessRateAttribute()
	{
		return number_format($this->success_rate, 1) . '%';
	}

	/**
	 * Get formatted open rate
	 */
	public function getFormattedOpenRateAttribute()
	{
		return number_format($this->open_rate, 1) . '%';
	}

	/**
	 * Get formatted click rate
	 */
	public function getFormattedClickRateAttribute()
	{
		return number_format($this->click_rate, 1) . '%';
	}

	/**
	 * Get formatted bounce rate
	 */
	public function getFormattedBounceRateAttribute()
	{
		return number_format($this->bounce_rate, 1) . '%';
	}
}

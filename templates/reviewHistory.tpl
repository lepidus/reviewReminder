<div class="pkp_review_history">
	<h4>{translate key="plugins.generic.reviewReminder.displayName"}</h4>
	{foreach from=$reviewReminderDates item="date"}
		<div>
			<strong>{$date|date_format:$datetimeFormatShort}</strong>
			{translate key="plugins.generic.reviewReminder.history.pendingReviewsReminder"}
		</div>
	{/foreach}
</div>

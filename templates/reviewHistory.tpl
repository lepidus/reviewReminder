<div class="pkp_review_history">
	<h4>{translate key="plugins.generic.reviewReminder.displayName"}</h4>
	{foreach from=$reviewReminderEntries item="entry"}
		<div>
			<strong>{$entry.date|date_format:$datetimeFormatShort}</strong>
			{translate key=$entry.localeKey}
		</div>
	{/foreach}
</div>

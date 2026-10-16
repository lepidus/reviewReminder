<div class="pkp_review_history">
	{foreach from=$reviewHistoryEntries item="entry"}
		<div>
			<strong>{$entry.date|date_format:$datetimeFormatShort}</strong>
			{translate key=$entry.localeKey}
		</div>
	{/foreach}
</div>

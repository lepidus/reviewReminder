<script>
	$(function() {ldelim}
		$('#reviewReminderSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="reviewReminderSettingsForm" method="post" action="{url router=\PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="common/formErrors.tpl"}

	{fbvFormArea id="reviewReminderSettingsFormArea"}
		{fbvFormSection list=true description="plugins.generic.reviewReminder.settings.pendingReviewsReminder.description"}
			{fbvElement type="checkbox" id="pendingReviewsReminderEnabled" value="1" checked=$pendingReviewsReminderEnabled label="plugins.generic.reviewReminder.settings.pendingReviewsReminder"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>

<x-mail::message>
<h1>@lang('version-deprecation-notice.title')</h1>

@lang('version-deprecation-notice.version_soon_deprecated', ['apiVersion' => $apiVersion])<br>
@lang('version-deprecation-notice.update_client_implementations')<br>
<br>
@lang('version-deprecation-notice.thanks'),<br>
@lang('version-deprecation-notice.your_team', ['appName' => config('app.name')])
</x-mail::message>

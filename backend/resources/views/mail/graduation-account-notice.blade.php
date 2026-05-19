<x-mail::message>
# Back up your school account

Hello {{ $studentName }},

You recently graduated. Your school email account **will be suspended on {{ $suspendOn->toFormattedDateString() }}** (in about {{ $daysUntilSuspend }} day(s)).

Before that date, please:

- Download or forward any email you need to keep
- Save files from Google Drive, Classroom, or other linked services
- Update any services that use your school email for sign-in

After suspension, you may lose access to mail and files stored in this account. The account may later be deleted per school policy.

If you have questions, contact your IT help desk.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

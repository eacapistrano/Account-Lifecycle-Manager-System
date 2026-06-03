<x-mail::message>
# Back up your school account before deletion

Hello {{ $studentName }},

Your school email account **will be deleted on {{ $deleteOn->toFormattedDateString() }}** (in about {{ $daysUntilDelete }} day(s)).

Please back up everything you need before that date:

- Download or forward important email
- Save files from Google Drive, Classroom, or other linked services
- Update any services that use your school email for sign-in

Once deleted, this account and its data cannot be recovered.

If you have questions, contact your IT help desk.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

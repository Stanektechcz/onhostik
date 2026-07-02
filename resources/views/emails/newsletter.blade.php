<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{{ $campaign->subject }}</title>
<style>
    body { margin:0; padding:0; background:#f4f4f4; font-family: Arial, Helvetica, sans-serif; }
    .wrapper { max-width:600px; margin:0 auto; background:#ffffff; }
    .footer { background:#f8f9fa; padding:20px; text-align:center; font-size:12px; color:#888; }
    a { color:#7366FF; }
</style>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4; padding:20px 0;">
    <tr>
        <td>
            <table class="wrapper" width="600" cellpadding="0" cellspacing="0" align="center">
                <tr>
                    <td style="padding:20px;">
                        {!! $campaign->body_html !!}
                    </td>
                </tr>
                <tr>
                    <td class="footer">
                        <p style="margin:0 0 8px;">
                            Dostáváte tento e-mail, protože jste přihlášen/a k odběru novinek.
                        </p>
                        <p style="margin:0;">
                            <a href="{{ $unsubscribeUrl }}">Odhlásit se z odběru</a>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8" />
</head>
<body style="font-family: sans-serif;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top: 20px;">
        <tr>
            <td align="left" bgcolor="#ffffff" style="padding: 10px; font-family: Arial, Helvetica, sans-serif; border-top: 3px solid #000F9F;">
                <p>Dear {{$name}},</p>
                <p>Please follow the link below to reset your password.</p>
                <p><a href="{{$forgot_url}}" target='_blank'>Reset Password</a></p>
                <p>Thank you,<br></p>
                <div style="margin-bottom: 0px;float: left;">
                    <p><b>NEWMARK PRIVATE CAPITAL GROUP</b><br>
                        <span style="font-size: 13px;margin-top: 0px;">1875 Century Park E, Suite 1380<br>Los Angeles, CA 90067<br><a href="mailto:NewmarkPCG@ngkf.com" style="color:#000f9f" >NewmarkPCG@ngkf.com</a></span><br>
                        <br><a style="font-size: 13px;color:#000f9f" href="'.site_url().'" target="_blank" >NewmarkPCG.com</a></p>
                </div>
            </td>
        </tr>
    </table>
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0px auto;">
        <tr>
            <td align="center" bgcolor="#e9ecef" style="padding: 12px 24px; font-family: Arial, Helvetica, sans-serif; line-height: 20px; color: #666;">
                <p style="margin: 0;font-size: 13px;">{{date('Y')}} © Newmark</p>
            </td>
        </tr>
    </table>
</body>
</html>



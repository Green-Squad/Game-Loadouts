<!DOCTYPE html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <p>Hello {{ $username }},</p>
        <p>
            You have just recently registered for <a href="http://www.gameloadouts.com">GameLoadouts.com</a>. You will need to confirm your email address by clicking the button below.
        </p>
        <p>
            <a style="display: inline-block;
                padding: 6px 12px;
                margin-bottom: 0;
                font-size: 14px;
                font-weight: normal;
                line-height: 1.428571429;
                text-align: center;
                text-decoration: none;
                white-space: nowrap;
                vertical-align: middle;
                cursor: pointer;
                border: 1px solid transparent;
                border-radius: 4px;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                -o-user-select: none;
                user-select: none;
                color: #fff;
                background-color: #428bca;
                border-color: #357ebd;" href="{{ URL::to('user/confirm', array($token)) }}">Click here to confirm</a>
        </p>
    </body>
</html>
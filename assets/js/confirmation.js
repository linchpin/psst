import jquery from 'jquery';
import clipboard from 'clipboard';
let $ = jquery;

export default function confirmation() {

  var clipboard = new ClipboardJS('.psst');

  clipboard.on('success', function(event) {
    $( '#copy-secret' ).foundation('show');
  });

  clipboard.on('error', function(event) {
    console.error('Action:', e.action);
    console.error('Trigger:', e.trigger);
  });

}

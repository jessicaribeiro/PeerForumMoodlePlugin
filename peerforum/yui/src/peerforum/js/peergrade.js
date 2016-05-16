YUI().use('editor', function(Y) {


    //Create the Base Editor
    var editor = new Y.EditorBase({
        content: '<p><b>This is <i class="foo">a test</i></b></p><p><b style="color: red; font-family: Comic Sans MS">This is <span class="foo">a test</span></b></p>',
        extracss: '.foo { font-weight: normal; color: black; background-color: yellow; }'
    });

    //Rendering the Editor
    editor.render('#editor');





    YAHOO.util.Event.on('#postpeergradesubmit', 'click', function() {
       //Put the HTML back into the text area
       myEditor.saveHTML();

       //The var html will now have the contents of the textarea
       var htmltest = myEditor.get('element').value;

       document.getElementbyId("printHere").innerHTML = htmltest;

       });

    /*var theinputs = Y.one('#feedbacktext');



  var out_feedback = Y.one('#outfeedback');

  out_feedback.set('innerHTML','OLA');


  var btn_submit_Click = function(e)
  {
    alert('Button clicked');
    theinputs.set('value', 'new feedback!');

    out_feedback.set('innerHTML','ADEUS');

  };
 // Y.on("click", btn_submit_Click, "#postpeergradesubmit");
*/

});

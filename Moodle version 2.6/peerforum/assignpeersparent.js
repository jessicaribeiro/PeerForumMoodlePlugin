function peerforum_assignpeersparent(e, obj) {
    api: M.cfg.wwwroot + '/mod/peerforum/assignpeersparent.php',

    e.preventDefault();

    Y.log('Enetered method assignpeersparent');
    var itemid = obj.itemid;
    var courseid = obj.courseid;
    var postauthor = obj.postauthor;

    var ioconfig = {
        method: 'POST',
        data: {'itemid' : itemid, 'courseid' : courseid, 'postauthor' : postauthor},
        on: {
            success: function (o, response) {
              var data = Y.JSON.parse(response.responseText);

              if (data.result) {

                  var id = 'nonepeers' + itemid;
                  var actionlink = 'actionlinkpeers' + itemid;
                  var peersassigned = 'peersassigned' + itemid;
                  var menuassignpeer = 'menuassignpeer' + itemid;
                  var menuremovepeer = 'menuremovepeer' + itemid;

                  var div_none_peergraders = document.getElementById(id);

                  document.getElementById(id).style.display = "none";

                  //document.getElementById(actionlink).style.display = "none";

                  document.getElementById(peersassigned).innerHTML = data.peersnames;

                  document.getElementById(menuassignpeer).innerHTML = data.canassign;

                  document.getElementById(menuremovepeer).innerHTML = data.canremove;

              }
            },
            failure: function (o, response) {
              alert('Error on peerforum_assignpeersparent');
            }
         }
    };

    Y.io(M.cfg.wwwroot + '/mod/peerforum/assignpeersparent.php', ioconfig);
}




/*M.core_peerforum_assignpeersparent = {

    Y : null,
    api: M.cfg.wwwroot + '/mod/peerforum/assignpeersparent.php',

    init : function(Y){
        this.Y = Y;
        Y.all('input.assignpeersparent').each(this.attach_peergrade_events, this);

    },


    attach_peergrade_events : function(selectnode) {

        selectnode.on('click', this.submit_peergrade, this, selectnode);

    },

    submit_peergrade : function(e, selectnode){

        var theinputs = selectnode.ancestor('form').all('.studentinput');
        var thedata = [];

        var inputssize = theinputs.size();
        for (var i = 0; i < inputssize; i++) {
            if(theinputs.item(i).get("name") != "returnurl") { // Dont include return url for ajax requests.
                thedata[theinputs.item(i).get("name")] = theinputs.item(i).get("value");
            }
        }

        var scope = this;
        var itemid = thedata['itemid'];

        var cfg = {
            method: 'POST',
            data: {'itemid' : itemid},
            on: {
                success: function (o, response) {
                  var data = Y.JSON.parse(response.responseText);

                  if (data.result) {
                      alert('OK');

                      var node = scope.Y.one('#peersassigned' + itemid);
                      node.set('innerHTML', data.peersnames);

                      var node = scope.Y.one('#menuassignpeer' + itemid);
                      node.set('innerHTML', data.canassign);

                      var node = scope.Y.one('#menuremovepeer' + itemid);
                      node.set('innerHTML', data.canremove);
                  }
                },
                failure: function (o, response) {
                  alert('Error on peerforum_assignpeersparent');
                }
             }
        };
        this.Y.io(this.api, cfg);

        }
    };
*/

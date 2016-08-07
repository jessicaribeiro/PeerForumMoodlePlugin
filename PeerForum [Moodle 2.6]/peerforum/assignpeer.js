M.core_peerforum_assignpeer = {
    Y : null,
    api: M.cfg.wwwroot + '/mod/peerforum/assignpeer.php',

    init : function(Y){
        this.Y = Y;
        Y.all('select.menuassignpeer').each(this.attach_assign_events, this);
    },

    attach_assign_events : function(selectnode) {
        selectnode.on('change', this.assign_peer, this, selectnode);
    },

    assign_peer : function(e, selectnode){
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
        var peerid = thedata['menuassignpeer'+itemid];
        var courseid = thedata['courseid'];
        var postauthor = thedata['postauthor'];

        var cfg = {
            method: 'POST',
            data: {'itemid' : itemid, 'peerid' : peerid, 'courseid' : courseid, 'postauthor' : postauthor},
            on: {
                success: function (o, response) {
                  var data = Y.JSON.parse(response.responseText);

                  if (data.result) {

                      var node = scope.Y.one('#menuassignpeer' + itemid);
                      node.set('innerHTML', data.canassign);

                      var node = scope.Y.one('#menuremovepeer' + itemid);
                      node.set('innerHTML', data.canremove);

                      if(data.peersnames == null){
                          var id = 'nonepeers' + itemid;
                          document.getElementById(id).style.display = "block";

                      } else {
                          var node = scope.Y.one('#peersassigned' + itemid);
                          node.set('innerHTML', data.peersnames);

                          var id = 'nonepeers' + itemid;
                          document.getElementById(id).style.display = "none";
                      }
                  }
                },
                failure: function (o, response) {
                  alert('Error on peerforum_assignpeer');
                }
             }
        };
        this.Y.io(this.api, cfg);
        }
    };

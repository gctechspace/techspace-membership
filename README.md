# Techspace Membership Plugin

Simple WordPress plugin to store membership details and facilitate door access via the WordPress API.

Integration with Xero to check for membership payment status.

RFID key storage and access restrictions.


## Usage:

Upload this to wp-content/plugin/techspace-membership/ and then activate from the WordPress plugins menu.

Look for the new "Members" and "RFID" menu that appears on the left hand side of WordPress

## Screenshots:

List of members, showing integration with Xero, linking RFID card to a member, and controlling what access that member has (which room/equipment):

![member list](http://i.imgur.com/y1cjbrt.jpg)

RFID key swipe access log. Any swipe activity on doors will appear here.

![rfid history](http://i.imgur.com/MT56B05.jpg)


## API Access:

Details on how to query the membership database via the WordPress API:

```
# using https://github.com/rodrigosclosa/ESP8266RestClient
RestClient client = RestClient("gctechspace.org",443);
client.setSecureConnection(true);
client.begin("ssid", "password");
String response = "";
response = "";
int statusCode = client.post("/api/rfid/123456789123456/room-3", "secret=secretcodehere", &response);
```


## Dev notes:

Go into Settings > TechSpace Members and add the Xero Public/Private keys along with choosing your own API secret key for ESP access.

if you get 404 trying to access API, go to Settings > Permalinks in WordPress and click the save button. This clears the URL rewrite cache.


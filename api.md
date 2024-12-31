# API reference
Clients can use the API to communicate with the backend. To do this, they send a POST request with a JSON body to the server. The structure of the body usually contains the parameters `action`, `data` and `client`. The parameter `action` generally defines the task of the request. The unique ID of the client is transmitted in the `client` field. The data required for processing is transported in the `data` field.

## clientCheck
This request should be the first request sent by a client. The client is registered on the server or, if it is already registered, updated on the server.

### Parameter

> |Parameter|Description|
> |-:|-|
> |action|clientCheck|
> |client|Unique ID of the client|
> |data|"usebasic" specifies whether BasicAuth should be used|

### Example

```
{
  "action": "clientCheck",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9",
  "data": {
    "usebasic": false
  }
}
```

## clientInfo
Requests information about the client from the server. In the response, returns the clear name stored on the server as well as the date when the client last performed an action on the server and information about the last IP address used.

### Parameter

> |Parameter|Description|
> |-:|-|
> |action|clientInfo|
> |client|Unique ID of the client|

### Example

```
{
  "action": "clientInfo",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9"
}
```

## clientList
Retrieves a list of clients for the user from the server. The response contains a list of all clients of the user, without the currently used client, but additionally a general client.

### Parameter
> |Parameter|Description|
> |-:|-|
> |action|clientInfo|
> |client|Unique ID of the client|

### Example
```
{
  "action": "clientList",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9"
}
```

## pushURL
Pushes a URL to a specific or all clients so that this URL appears as a notification on this client. Depending on the server settings, the notification can also be sent via ntfy

### Parameter
> |Parameter|Description|
> |-:|-|
> |action|pushURL|
> |client|Unique ID of the client|
> |data|Contains the URL and unique client id of the target|

### Example
```
{
  "action": "pushURL",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9",
  "data": {
    "url": "https://jsoneditoronline.org/#left=local.pepeyu",
    "target": "9f2b9ffg-65f4-4b5b-866h-61d9c1f5zf7a"
  }
}
```

## pushGet
This request fetches all pushed URLs that are actively stored on the server. After the response, a notification is displayed on the client for each of these URLs. If you click on this, the URL is loaded in a new tab and the URL is marked as inactive on the server.

### Parameter
> |Parameter|Description|
> |-:|-|
> |action|pushGet|
> |client|Unique ID of the client|

### Example
```
{
  "action": "pushGet",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9"
}
```

## pushHide
This removes a URL once it has been pushed. This request is sent when the URL has been displayed. The URL is then still contained in the history.

### Parameter
> |Parameter|Description|
> |-:|-|
> |action|pushHide|
> |client|Unique ID of the client|
> |data|ID of the URL to be removed|

### Example
```
{
  "action": "pushHide",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9",
  "data": 12
}
```

## tabsGet
This request is sent to the server if the synchronization of tabs is activated. It retrieves the list of tabs saved on the server. All URLs in the list are automatically opened as a new tab in the browser.

### Parameter
> |Parameter|Description|
> |-:|-|
> |action|tabsGet|
> |client|Unique ID of the client|

### Example
```
{
  "action": "tabsGet",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9"
}
```

## tabsSend
If the synchronization of tabs is active, this request is sent to the server when a new tab is opened, closed or changed. The request contains all tabs that have opened a website but no tabs that are empty or internal URLs.

### Parameter
> |Parameter|Description|
> |-:|-|
> |action|tabsSend|
> |client|Unique ID of the client|
> |data|List of tabs with open pages. The windowID shown in the example identifies the open browser window, but it is not evaluated on the server. However, the URL and the title are required. This request is sent by the browser when a new tab is opened, changed or closed.|

### Example
```
{
  "action": "tabsSend",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9",
  "data": [
    {
      "windowId": 1,
      "url": "https://www.google.com/",
      "title": "Google"
    }
  ]
}
```

## bookmarkExport
All of the user's bookmarks are requested from the server. After the response, the bookmarks are imported to the client. Based on the data provided, the client decides whether the bookmark already exists or has been changed, or whether it needs to be deleted or moved.

### Parameter
> |Parameter|Description|
> |-:|-|
> |action|bookmarkExport|
> |client|Unique ID of the client|
> |data|json \| html|

### Example
```
{
  "action": "bookmarkExport",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9",
  "data": "json"
}
```

## bookmarkImport
Sent to the server after the local bookmarks have been read. Contains the list of all local bookmarks in a JSON hierarchy. These bookmarks are imported at the server.

### Parameter
> |Parameter|Description|
> |-:|-|
> |action|bookmarkImport|
> |client|Unique ID of the client|
> |data|List of local bookmarks in a JSON hierarchy.|


## bookmarkAdd
This request adds a new bookmark to the server. The response indicates whether the bookmark has been added.

### Parameter
> |Parameter|Description|
> |-:|-|
> |action|bookmarkAdd|
> |client|Unique ID of the client|
> |data|JSON representation of the bookmark. A unique ID is required, the `url`, the `title` is optional. The possible `type` are bookmark or folder. The `folder` field contains the ID of the folder and `nfolder` contains the plain text name of this folder. The `added` field contains the timestamp of when the bookmark was added.|

### Example
```
{
  "action": "bookmarkAdd",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9",
  "data": {
    "id": "5FhRR5GP7fght",
    "url": "https://www.google.com/",
    "title": "Google",
    "type": "bookmark",
    "folder": "unfiled_____",
    "nfolder": "Other Bookmarks",
    "added": 1727006158208
  }
}
```

## bookmarkMove
The bookmark is moved with this request. Either in the position in the same folder to another position or from one folder to another folder.

### Parameter
> |Parameter|Description|
> |-:|-|
> |action|bookmarkMove|
> |client|Unique ID of the client|
> |data|JSON data required for moving. In this case, the internal `id`, the `index` (position of the bookmark in the folder), `folderIndex` for the position of the folder, `folder` with the internal ID of the folder, `nfolder` with the clear name of the folder and in `url`, the URL for the bookmark|

### Example
```
{
  "action": "bookmarkMove",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9",
  "data": {
    "id": "5FhRR5GP7fght",
    "index": 55,
    "folderIndex": 3,
    "folder": "unfiled_____",
    "nfolder": "Other Bookmarks",
    "url": "https://www.google.com/"
  }
}
```

## bookmarkEdit
After the bookmark has been edited on a client, this request is sent to the server in order to transfer the change to the bookmark to the server.

### Parameter
> |Parameter|Description|
> |-:|-|
> |action|bookmarkEdit|
> |client|Unique ID of the client|
> |data|JSON data to change the bookmark on the server or to be able to identify it. The title field contains the changed name of the bookmark, the internal ID of the respective folder and the index field contains the position of the bookmark|

### Example
```
{
  "action": "bookmarkEdit",
  "client": "c0dfd20c-122e-48f1-ba6d-c5632rf08d9",
  "data": {
    "title": "Google",
    "parentId": "unfiled_____",
    "index": 57
  }
}
```
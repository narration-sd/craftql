![CraftQL seen through the GraphiQL UI](https://raw.githubusercontent.com/markhuot/craftql/master/assets/graphiql.png)

A drop-in GraphQL server for your [Craft CMS](https://craftcms.com/) implementation. With zero configuration, _CraftQL_ allows you to access all of Craft's features through a familiar [GraphQL](http://graphql.org) interface.

<hr>

**NOTE:** This software is in beta and while querying the database works quite well it has not been thoroughly tested. Use at your own risk.

**P.P.S**, this plugin may or may not become a paid add-on when the Craft Plugin store becomes available. <strike>Buyer</strike> Downloader beware.

<hr>

## Example

Once installed, you can test your installation with a simple Hello World,

```graphql
{
  helloWorld
}
```

If that worked, you can now query Craft CMS using almost the exact same syntax as your Twig templates.

```graphql
{
  entries(section:"news", limit:5, search:"body:salty") {
    ...on News {
      title
      url
      body
    }
  }
}
```

_CraftQL_ takes a the convention over configuration approach today. That means the following types and fields are automatically provided for you.

A top level `entries` field on `Query` that takes the same arguments as `craft.entries` does in your template. E.g.,

```graphql
query fetchNews {             # The query
  entries(section:"News") {   # Arguments match `craft.entries`
    ...on News {              # GraphQL is strongly typed, so you must specify each Entry Type you want data from
      id                      # A field to return
      title                   # A field to return
      body                    # A field to return
    }
  }
}
```

Types for every Entry Type in your install. If you have a section named `news` and an entry type named `news` the GraphQL type will be named `News`. If you have a section named `news` and an entry type named `pressRelease` the GraphQL type will be named `NewsPressRelease`. The convention is to mash the section handle and the entry type together, unless they are the same, in which case the section handle will be used.

```graphql
query fetchNews {
  entries(section:"News") {
    ...on News {              # Any fields on the News entry type
      id
      title
      body
    }
    ...on NewsPressRelease {  # Any fields on the Press Release entry type
      id
      title
      body
      source
      contactPeople {         # A nested Entries relationship
        name
        email
      }
    }
  }
}
```

A top level `upsertEntry` on `Mutation` that takes arguments of every field defined in Craft. 

```graphql
mutation createNewEntry($title:String, $body:String) {
  upsertEntry(
    sectionId:1,
    typeId:1,
    authorId:1,
    title:$title,
    body:$body,
  ) {
    id
  }
}
```

The above would be passed with variables such as,

```json
{
  "title": "My first mutation!",
  "body": "<p>Here's the body of my first mutation</p>",
}
```

## Requirements

- Craft 3.0
- PHP 7.0+

## Installation

If you don't have Craft 3 installed yet, do that first:

```shell
$ composer create-project craftcms/craft my-awesome-site -s beta
```

Once you have a running version of Craft 3 you can install _CraftQL_ with Composer:

```shell
$ composer require markhuot/craftql
```

## Running the CLI server

_CraftQL_ ships with a PHP-native web server. When running _CraftQL_ through the provided web server the bootstrapping process will only happen during the initial start up. This has the potential to greatly speed up responses times since PHP will persist state between requests. In general, I have seen performance improvements of 5x (500ms to <100ms).

Caution: this can also create unintended side effects since Craft is not natively built to run this way. Do not use this in production it could lead to memory leaks, server fires, and IT pager notifications :).

```
php craft craftql/server
```

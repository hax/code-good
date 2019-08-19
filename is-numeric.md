关于 jQuery.isNumeric：

`jQuery.isNumeric()` 已经被 deprecated，在未来的jQuery 4.0里会被删除。

历史上的实现变迁：

2011-11-03 jQuery 1.7.0
```js
rdigit = /\d/,
isNumeric: function( obj ) {
	return obj != null && rdigit.test( obj ) && !isNaN( obj );
},
```
2011-11-21 jQuery 1.7.1
2013-04-18 jQuery 2.0.0
```js
isNumeric: function( obj ) {
	return !isNaN( parseFloat(obj) ) && isFinite( obj );
},
```

2014-01-23 jQuery 1.11.0, 2.1.0
```js
isNumeric: function( obj ) {
	// parseFloat NaNs numeric-cast false positives (null|true|false|"")
	// ...but misinterprets leading-number strings, particularly hex literals ("0x...")
	// subtraction forces infinities to NaN
	return obj - parseFloat( obj ) >= 0;
},
```
2014-05-01 jQuery 1.11.1, 2.1.1
```js
isNumeric: function( obj ) {
	// parseFloat NaNs numeric-cast false positives (null|true|false|"")
	// ...but misinterprets leading-number strings, particularly hex literals ("0x...")
	// subtraction forces infinities to NaN
	return !jQuery.isArray( obj ) && obj - parseFloat( obj ) >= 0;
},
```
2014-12-17 jQuery 1.11.2, 2.1.2
```js
isNumeric: function( obj ) {
	// parseFloat NaNs numeric-cast false positives (null|true|false|"")
	// ...but misinterprets leading-number strings, particularly hex literals ("0x...")
	// subtraction forces infinities to NaN
	// adding 1 corrects loss of precision from parseFloat (#15100)
	return !jQuery.isArray( obj ) && (obj - parseFloat( obj ) + 1) >= 0;
},
```
2016-01-08 jQuery 1.12.0, 2.2.0
```js
isNumeric: function( obj ) {
	// parseFloat NaNs numeric-cast false positives (null|true|false|"")
	// ...but misinterprets leading-number strings, particularly hex literals ("0x...")
	// subtraction forces infinities to NaN
	// adding 1 corrects loss of precision from parseFloat (#15100)
	var realStringObj = obj && obj.toString();
	return !jQuery.isArray( obj ) && ( realStringObj - parseFloat( realStringObj ) + 1 ) >= 0;
},
```
2016-06-09 jQuery 3.0.0
```js
isNumeric: function( obj ) {
	// As of jQuery 3.0, isNumeric is limited to
	// strings and numbers (primitives or objects)
	// that can be coerced to finite numbers (gh-2662)
	var type = jQuery.type( obj );
	return ( type === "number" || type === "string" ) &&
		// parseFloat NaNs numeric-cast false positives ("")
		// ...but misinterprets leading-number strings, particularly hex literals ("0x...")
		// subtraction forces infinities to NaN
		!isNaN( obj - parseFloat( obj ) );
},
```

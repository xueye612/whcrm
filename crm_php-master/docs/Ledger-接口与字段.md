瀹㈡埛鍙拌处 - 鎺ュ彛涓庡瓧娈?
妯″潡
- 妯″潡鍚嶏細ledger
- 璺敱鏂囦欢锛歚config/route_ledger.php`

缁熶竴杩斿洖缁撴瀯
```
{ "code": 200, "data": ..., "error": "" }
```

鎺ュ彛锛圥OST锛?- `/ledger/index`
- `/ledger/read`
- `/ledger/save`
- `/ledger/update`
- `/ledger/delete`

瀛楁锛?kcrm_customer_ledger锛?- ledger_id 涓婚敭
- customer_id 瀹㈡埛ID
- title 鍙嶉闂
- description 闂鎻忚堪
- feedback_user 鍙嶉浜?- category 闂鍒嗙被锛堟柊闇€姹?BUG/鎿嶄綔鎸囧/鍏朵粬锛?- status 澶勭悊鐘舵€侊紙寰呭鐞?澶勭悊涓?寰呴獙璇?宸插畬鎴?宸插叧闂級
- register_time 鐧昏鏃堕棿锛堟椂闂存埑锛?- finish_time 澶勭悊瀹屾垚鏃堕棿锛堟椂闂存埑锛?- register_user_id 鐧昏浜?- handler_user_id 澶勭悊浜?- remark 澶囨敞
- create_time/update_time

绛涢€夊弬鏁帮紙index锛?- customer_id
- status
- category
- register_time 鍖洪棿锛歴tart_date / end_date
- handler_user_id
- keyword锛堟ā绯婃悳绱細title/description/feedback_user/remark锛?- page / limit

SQL
- `sql/upgrade_customer_ledger.sql`


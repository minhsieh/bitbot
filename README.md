Bitbot
======

This is a Binance auto trading bot with very simple strategy.

There are some keys:

- Entry condition types have: 
    - Random
    - MA break
    - Always one way (always buy or always sell)
    
- Tail profit tracking


## Payload sample

AggTrade payload

```
{
  "p" : "57454.40",         // 成交價格
  "E" : 1617790116126,      // 成交時間
  "l" : 672356776,          // 被归集的末次交易ID
  "q" : "0.001",            // 成交數量
  "m" : true,               // 买方是否是做市方。如true，则此次成交是一个主动卖出单，否则是一个主动买入单。
  "e" : "aggTrade",         // 事件类型
  "a" : 418678276,          // 归集成交 ID
  "T" : 1617790116120,      // 成交時間
  "s" : "BTCUSDT",          // 成交幣別
  "f" : 672356776           // 被归集的首个交易ID
}
```

MarkPrice payload

```
{
    "e": "markPriceUpdate",     // 事件类型
    "E": 1562305380000,         // 事件时间
    "s": "BTCUSDT",             // 交易对
    "p": "11794.15000000",      // 标记价格
    "i": "11784.62659091",      // 现货指数价格
    "P": "11784.25641265",      // 预估结算价,仅在结算前最后一小时有参考价值
    "r": "0.00038167",          // 资金费率
    "T": 1562306400000          // 下次资金时间
}
```

Trade API payload

```
{
    "clientOrderId": "testOrder", // 用户自定义的订单号
    "cumQty": "0",
    "cumQuote": "0", // 成交金额
    "executedQty": "0", // 成交量
    "orderId": 22542179, // 系统订单号
    "avgPrice": "0.00000",  // 平均成交价
    "origQty": "10", // 原始委托数量
    "price": "0", // 委托价格
    "reduceOnly": false, // 仅减仓
    "side": "SELL", // 买卖方向
    "positionSide": "SHORT", // 持仓方向
    "status": "NEW", // 订单状态
    "stopPrice": "0", // 触发价，对`TRAILING_STOP_MARKET`无效
    "closePosition": false,   // 是否条件全平仓
    "symbol": "BTCUSDT", // 交易对
    "timeInForce": "GTC", // 有效方法
    "type": "TRAILING_STOP_MARKET", // 订单类型
    "origType": "TRAILING_STOP_MARKET",  // 触发前订单类型
    "activatePrice": "9020", // 跟踪止损激活价格, 仅`TRAILING_STOP_MARKET` 订单返回此字段
    "priceRate": "0.3", // 跟踪止损回调比例, 仅`TRAILING_STOP_MARKET` 订单返回此字段
    "updateTime": 1566818724722, // 更新时间
    "workingType": "CONTRACT_PRICE", // 条件价格触发类型
    "priceProtect": false            // 是否开启条件单触发保护
}
```

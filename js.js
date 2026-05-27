
/**
 * 1. 工具函数：生成 SHA256HEX 大写签名
 * @param {string} str 待加密的原始字符串
 * @returns {Promise<string>} 64位大写16进制签名结果
 */
async function getSHA256HEX(str) {
  try {
    // 字符串转 UTF-8 二进制数组
    const encoder = new TextEncoder();
    const uint8Array = encoder.encode(str);
    // 执行 SHA256 加密
    const hashBuffer = await crypto.subtle.digest('SHA-256', uint8Array);
    // 二进制转 16 进制字符串（大写）
    const uint8Hash = new Uint8Array(hashBuffer);
    let hexStr = '';
    uint8Hash.forEach(byte => {
      hexStr += byte.toString(16).padStart(2, '0').toUpperCase();
    });
    return hexStr;
  } catch (error) {
    console.error("SHA256 加密失败：", error);
    throw error;
  }
}
getSHA256HEX('accessKey=EKGrsveyb8W6uQl9jVGxTm3BUJ6NbdGF&nonce=1234&secretKey=px5nTLMQv5UdWnqhWpFbMMbrwq0WvTFN&timestamp=1624939232345')
/**
 * 2. 生成 51.la 接口鉴权参数（含 sign）
 * @param {object} config 接口配置参数
 * @returns {Promise<object>} 带签名的完整请求参数
 */
async function generateAuthParams(config) {
  // 基础参数（按要求顺序）
  const baseParams = {
    maskId: config.maskId,
    accessKey: config.accessKey,
    nonce: config.nonce || Math.floor(1000 + Math.random() * 9000).toString(),
    timestamp: config.timestamp || new Date().getTime().toString()
  };

  // 严格按顺序拼接：accessKey=xxx&nonce=xxx&timestamp=xxx
  const paramStr = `accessKey=${baseParams.accessKey}&nonce=${baseParams.nonce}&timestamp=${baseParams.timestamp}`;
  // 拼接 secretKey 得到待加密字符串
  const rawSignStr = paramStr + config.secretKey;
  // 生成签名
  const sign = await getSHA256HEX(rawSignStr);

  // 返回带 sign 的完整参数
  return { ...baseParams, sign };
}

/**
 * 3. 调用 51.la 访客详情接口
 * @param {object} config 接口配置
 * @returns {Promise<object>} 接口返回数据
 */
// async function call51LaVisitorApi(config) {
//   const baseUrl = "https://v6-open.51.la/open/visitor/detail/list";

//   try {
//     // 生成鉴权参数
//     const authParams = await generateAuthParams(config);
//     console.log("生成的请求参数：", authParams);

//     // 拼接 GET 请求 URL
//     const urlParams = new URLSearchParams(authParams);
//     const fullUrl = `${baseUrl}?${urlParams.toString()}`;
//     console.log("最终请求 URL：", fullUrl);

//     // 发起 fetch 请求
//     const response = await fetch(fullUrl, {
//       method: "GET",
//       headers: {
//         "Content-Type": "application/json;charset=UTF-8"
//       },
//       // 若有跨域问题，可尝试添加（需后端配合）
//       // mode: "cors"
//     });

//     // 响应状态校验
//     if (!response.ok) {
//       const errorText = await response.text();
//       throw new Error(`接口请求失败 [状态码: ${response.status}]，响应内容：${errorText}`);
//     }

//     // 解析响应数据（JSON 格式）
//     const result = await response.json();
//     console.log("✅ 接口调用成功，返回数据：", result);
//     return result;

//   } catch (error) {
//     console.error("❌ 接口调用异常：", error.message);
//     throw error; // 抛出错误，供上层业务处理
//   }
// }

// // ====================== 4. 实际调用示例 ======================
// (async () => {
//   // 替换为你的真实配置！！！
//   const apiConfig = {
//     maskId: "JSnwukePFgbt74gj", // 你的 maskId
//     accessKey: "EKGrsveyb8W6uQl9jVGxTm3BUJ6NbdGF", // 你的 accessKey
//     secretKey: "px5nTLMQv5UdWnqhWpFbMMbrwq0WvTFN", // 你的 secretKey（严禁暴露在前端源码！）
//     nonce: "1234", // 4位随机字符串，可固定或自动生成
//     timestamp: "1624939232345" // 13位毫秒时间戳，可固定或自动生成
//   };

//   // 执行接口调用
//   try {
//     await call51LaVisitorApi(apiConfig);
//   } catch (error) {
//     // 业务层错误处理
//     console.log("业务处理失败：", error);
//   }
// })();
import request from "@/utils/request";

const BASE_URL = "/api/v1/{{entityKebab}}";

const {{entityKebab}}API = {
  /** 分页 */
  getPage(params) {
    return request({
      url: `${BASE_URL}`,
      method: "get",
      params,
    });
  },

  /** 表单 */
  getForm(id) {
    return request({
      url: `${BASE_URL}/${id}/form`,
      method: "get",
    });
  },

  /** 新增 */
  create(data) {
    return request({
      url: `${BASE_URL}`,
      method: "post",
      data,
    });
  },

  /** 修改 */
  update(id, data) {
    return request({
      url: `${BASE_URL}/${id}`,
      method: "put",
      data,
    });
  },

  /** 删除 */
  deleteByIds(ids) {
    return request({
      url: `${BASE_URL}/${ids}`,
      method: "delete",
    });
  },
};

export default {{entityKebab}}API;

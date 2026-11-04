<template>
  <CrudPage
    :search-config="searchConfig"
    :table-config="tableConfig"
    :form-config="formConfig"
    :load-form-data="loadFormData"
  >
{$searchSlotsCurd}
{$listSlotsCurd}
{$formSlotsCurd}
  </CrudPage>
</template>

<script setup>
import {$entityName}API from "@/api/{$moduleName}/{$entityKebab}";

defineOptions({ name: "{$entityName}", inheritAttrs: false });

const searchConfig = {
  fields: [
{$searchConfigItemsCurd}
  ],
};

const tableConfig = {
  permissionPrefix: "{$permPrefix}",
  table: { border: true },
  fetchData: {$entityName}API.getPage,
  deleteByIds: {$entityName}API.deleteByIds,
  toolbarActions: ["create", "delete"],
  tableTools: ["refresh", "columns", "search"],
  columns: [
    { type: "selection", width: 50 },
{$contentColsCurd}
    {
      prop: "actions",
      label: "操作",
      cellType: "actions",
      actions: ["view", "edit", "delete"],
      fixed: "right",
      width: 200,
    },
  ],
};

const formConfig = {
  title: ({ mode }) => ({
    create: "新增{$businessName}",
    edit: "编辑{$businessName}",
    view: "查看{$businessName}",
  })[mode],
  dialog: { width: 560 },
  /**
   * 保存记录，更新目标使用打开表单时的主键。
   */
  submitForm(data, { mode, id }) {
    if (mode === "create") return {$entityName}API.create(data);
    if (id === undefined) throw new Error("缺少记录 ID");
    return {$entityName}API.update(String(id), data);
  },
  fields: [
{$modalFormItemsCurd}
  ],
};

/**
 * 加载编辑和查看所需的详情。
 */
function loadFormData(row) {
  const rowKey = tableConfig.rowKey ?? "id";
  return {$entityName}API.getFormData(String(row[rowKey]));
}
</script>

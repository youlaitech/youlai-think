<template>
  <div class="page-container h-full flex flex-1 flex-col">
    <!-- 搜索 -->
    <PageSearch
      ref="searchRef"
      :search-config="searchConfig"
      @query-click="handleQueryClick"
      @reset-click="handleResetClick"
    >
{$searchSlotsCurd}
    </PageSearch>

    <!-- 列表 -->
    <PageContent
      ref="contentRef"
      :content-config="contentConfig"
      @add-click="handleAddClick"
      @export-click="handleExportClick"
      @search-click="handleSearchClick"
      @toolbar-click="handleToolbarClick"
      @operate-click="handleOperateClick"
      @filter-change="handleFilterChange"
    >
{$listSlotsCurd}
    </PageContent>

    <!-- 新增 -->
    <PageModal ref="addModalRef" :modal-config="addModalConfig" @submit-click="handleSubmitClick">
{$formSlotsCurd}
    </PageModal>

    <!-- 编辑 -->
    <PageModal ref="editModalRef" :modal-config="editModalConfig" @submit-click="handleSubmitClick">
{$formSlotsCurd}
    </PageModal>
  </div>
</template>

<script setup>
defineOptions({
  name: "{$entityName}",
  inheritAttrs: false,
});

import {$entityName}API from "@/api/{$moduleName}/{$entityKebab}";
import usePage from "@/components/CURD/usePage";

const {
  searchRef,
  contentRef,
  addModalRef,
  editModalRef,
  handleQueryClick,
  handleResetClick,
  handleAddClick,
  handleEditClick,
  handleSubmitClick,
  handleExportClick,
  handleSearchClick,
  handleFilterChange,
} = usePage();

const searchConfig = reactive({
  permPrefix: "{$moduleName}:{$entityKebab}",
  formItems: [
{$searchConfigItemsCurd}
  ],
});

const contentConfig = reactive({
  permPrefix: "{$moduleName}:{$entityKebab}",
  table: {
    border: true,
    highlightCurrentRow: true,
  },
  pk: "id",
  indexAction: {$entityName}API.getPage,
  deleteAction: {$entityName}API.deleteByIds,
  parseData(res) {
    return {
      total: res?.total ?? 0,
      list: res?.list ?? [],
    };
  },
  pagination: {
    background: true,
    layout: "total, sizes, prev, pager, next, jumper",
    pageSize: 20,
    pageSizes: [10, 20, 30, 50],
  },
  toolbar: ["add", "delete"],
  defaultToolbar: ["refresh", "filter"],
  cols: [
    { type: "selection", width: 55, align: "center" },
{$contentColsCurd}
    {
      label: "操作",
      prop: "operation",
      width: 180,
      templet: "tool",
      operat: ["edit", "delete"],
    },
  ],
});

const addModalConfig = reactive({
  permPrefix: "{$moduleName}:{$entityKebab}",
  pk: "id",
  dialog: {
    title: "新增{$businessName}",
    width: 800,
    draggable: true,
  },
  form: {
    labelWidth: 100,
  },
  formItems: [
{$modalFormItemsCurd}
  ],
  formAction(data) {
    if (data.id) {
      return {$entityName}API.update(data.id, data);
    } else {
      return {$entityName}API.create(data);
    }
  },
});

const editModalConfig = reactive({
  permPrefix: "{$moduleName}:{$entityKebab}",
  component: "drawer",
  drawer: {
    title: "编辑{$businessName}",
    size: 500,
  },
  pk: "id",
  formAction(data) {
    return {$entityName}API.update(data.id, data);
  },
  formItems: addModalConfig.formItems,
});

const handleOperateClick = (data) => {
  if (data.name === "edit") {
    handleEditClick(data.row, async () => {
      return await {$entityName}API.getFormData(String(data.row.id));
    });
  }
};

const handleToolbarClick = (name) => {
  console.log(name);
};
</script>

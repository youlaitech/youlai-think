<template>
  <div class="app-container h-full flex flex-1 flex-col">
    <!-- 搜索 -->
    <PageSearch
      ref="searchRef"
      :search-config="searchConfig"
      @query-click="handleQueryClick"
      @reset-click="handleResetClick"
    >
{{searchSlotsCurd}}
    </PageSearch>

    <!-- 列表 -->
    <PageContent
      ref="contentRef"
      :content-config="contentConfig"
      @add-click="handleAddClick"
      @export-click="handleExportClick"
      @search-click="handleSearchClick"
      @filter-change="handleFilterChange"
    >
{{listSlotsCurd}}
    </PageContent>

    <!-- 新增 -->
    <PageModal ref="addModalRef" :modal-config="addModalConfig" @submit-click="handleSubmitClick">
{{formSlotsCurd}}
    </PageModal>

    <!-- 编辑 -->
    <PageModal ref="editModalRef" :modal-config="editModalConfig" @submit-click="handleSubmitClick">
{{formSlotsCurd}}
    </PageModal>
  </div>
</template>

<script setup>
defineOptions({ name: "{{entityName}}" });

import {{entityName}}API from "@/api/{{moduleName}}/{{entityKebab}}";
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
  permPrefix: "{{moduleName}}:{{entityKebab}}",
  formItems: [
{{searchConfigItemsCurd}}
  ],
});

const contentConfig = reactive({
  permPrefix: "{{moduleName}}:{{entityKebab}}",
  table: {
    border: true,
    highlightCurrentRow: true,
  },
  pk: "id",
  indexAction: {{entityName}}API.getPage,
  deleteAction: {{entityName}}API.deleteByIds,
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
{{contentColsCurd}}
    {
      label: "操作",
      prop: "operation",
      width: 220,
      templet: "tool",
      operat: ["edit", "delete"],
    },
  ],
});

const addModalConfig = reactive({
  permPrefix: "{{moduleName}}:{{entityKebab}}",
  pk: "id",
  dialog: {
    title: "新增{{businessName}}",
    width: 800,
    draggable: true,
  },
  form: {
    labelWidth: 100,
  },
  formItems: [
{{modalFormItemsCurd}}
  ],
});

const editModalConfig = reactive({
  permPrefix: "{{moduleName}}:{{entityKebab}}",
  pk: "id",
  dialog: {
    title: "编辑{{businessName}}",
    width: 800,
    draggable: true,
  },
  form: {
    labelWidth: 100,
  },
  formItems: [
{{modalFormItemsCurd}}
  ],
  formAction: async (data) => {
    const id = data?.id;
    if (id) {
      return {{entityName}}API.update(id, data);
    }
    return {{entityName}}API.create(data);
  },
});
</script>

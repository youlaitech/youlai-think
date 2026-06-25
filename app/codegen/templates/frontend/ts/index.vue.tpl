<template>
  <div class="page-container">
    <!-- 搜索 -->
    <el-card class="page-search" shadow="never">
      <el-form ref="queryFormRef" :model="params" :inline="true">
{$searchFormItems}
        <el-form-item>
          <el-button type="primary" @click="handleQuery">搜索</el-button>
          <el-button @click="handleResetQuery">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 表格 -->
    <el-card ref="tableWrapperRef" class="page-content" shadow="never">
      <div class="page-toolbar">
        <div class="page-toolbar__left">
        <el-button
          v-hasPerm="['{$moduleName}:{$entityKebab}:create']"
          type="primary"
          @click="handleCreateClick()"
        >新增</el-button>
        <el-button
          v-hasPerm="['{$moduleName}:{$entityKebab}:delete']"
          type="danger"
          :disabled="!hasSelection"
          @click="handleBatchDelete()"
        >删除</el-button>
        </div>
        <div class="page-toolbar__right">
          <el-tooltip content="刷新" placement="top">
            <el-button class="page-icon-btn" @click="fetchData">
              <el-icon><Refresh /></el-icon>
            </el-button>
          </el-tooltip>
          <el-tooltip content="全屏" placement="top">
            <el-button class="page-icon-btn" @click="toggleFullscreen">
              <el-icon><FullScreen /></el-icon>
            </el-button>
          </el-tooltip>
        </div>
      </div>

      <div class="page-table-wrapper">
      <el-table
        ref="dataTableRef"
        v-loading="loading"
        class="page-table"
        :data="list"
        height="100%"
        highlight-current-row
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" align="center" />
{$tableColumns}
        <el-table-column fixed="right" label="操作" width="180">
          <template #default="scope">
            <el-button
              v-hasPerm="['{$moduleName}:{$entityKebab}:update']"
              type="primary"
              size="small"
              link
              @click="handleEditClick(String(scope.row.id))"
            >
              编辑
            </el-button>
            <el-button
              v-hasPerm="['{$moduleName}:{$entityKebab}:delete']"
              type="danger"
              size="small"
              link
              @click="handleDelete(String(scope.row.id))"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      </div>

      <pagination
        v-if="total > 0"
        v-model:total="total"
        v-model:page="params.pageNum"
        v-model:limit="params.pageSize"
        class="page-pagination"
        @pagination="fetchData"
      />
    </el-card>

    <el-dialog
      v-model="dialog.visible"
      :title="dialog.title"
      width="600px"
      @close="closeDialog"
    >
      <el-form ref="dataFormRef" :model="formData" :rules="rules" label-width="100px">
{$formItems}
      </el-form>
      <template #footer>
        <div class="dialog-footer">
          <el-button type="primary" @click="handleSubmit">确定</el-button>
          <el-button @click="closeDialog">取消</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { useFullscreen } from "@vueuse/core";
import {
  ElMessage,
  ElMessageBox,
  type FormInstance,
  type FormRules,
} from "element-plus";
import { FullScreen, Refresh } from "@element-plus/icons-vue";
import { usePageTable, useTableSelection } from "@/composables";
import {$entityName}API from "@/api/{$moduleName}/{$entityKebab}";
import type { {$entityName}Item, {$entityName}Form, {$entityName}QueryParams } from "@/api/{$moduleName}/{$entityKebab}";

defineOptions({
  name: "{$entityName}",
  inheritAttrs: false,
});

const queryFormRef = ref<FormInstance>();
const dataFormRef = ref<FormInstance>();
const tableWrapperRef = ref<HTMLElement | null>(null);
const { toggle: toggleFullscreen } = useFullscreen(tableWrapperRef);

const initialFormData = reactive({} as {$entityName}Form);

const { loading, list, total, params, fetchData, handleQuery, handleResetQuery } = usePageTable<
  {$entityName}Item,
  {$entityName}QueryParams
>({
  initialParams: {
    pageNum: 1,
    pageSize: 10,
  } as {$entityName}QueryParams,
  request: {$entityName}API.getPage,
  onBeforeReset: () => queryFormRef.value?.resetFields(),
});

const { selectedIds, hasSelection, handleSelectionChange } = useTableSelection<{$entityName}Item>();

const dialog = reactive({
  title: "",
  visible: false,
});

const formData = reactive<{$entityName}Form>({} as {$entityName}Form);

const rules: FormRules = {
{$rules}
};

/**
 * 打开表单弹窗
 */
function openDialog() {
  dialog.visible = true;
}

/**
 * 关闭弹窗并重置表单
 */
function closeDialog() {
  dialog.visible = false;
  resetForm();
}

function resetForm() {
  dataFormRef.value?.resetFields();
  dataFormRef.value?.clearValidate();
  Object.keys(formData).forEach((key) => {
    delete (formData as Record<string, unknown>)[key];
  });
  Object.assign(formData, initialFormData);
}

/**
 * 打开新增弹窗
 */
async function handleCreateClick(): Promise<void> {
  dialog.title = "新增{$businessName}";
  openDialog();
}

async function handleEditClick(id: string): Promise<void> {
  dialog.title = "修改{$businessName}";
  const data = await {$entityName}API.getFormData(id);
  Object.assign(formData, data);
  openDialog();
}

async function handleSubmit(): Promise<void> {
  const valid = await dataFormRef.value?.validate().then(
    () => true,
    () => false
  );
  if (!valid) return;

  loading.value = true;
  try {
    const id = formData.id;
    if (id) {
      await {$entityName}API.update(id, formData);
      ElMessage.success("修改成功");
    } else {
      await {$entityName}API.create(formData);
      ElMessage.success("新增成功");
    }
    closeDialog();
    handleResetQuery();
  } finally {
    loading.value = false;
  }
}

function handleBatchDelete() {
  handleDelete();
}

/**
 * 删除{$businessName}
 */
async function handleDelete(id?: string) {
  const ids = id || selectedIds.value.join(",");
  if (!ids) {
    ElMessage.warning("请勾选删除项");
    return;
  }

  try {
    await ElMessageBox.confirm("确认删除已选中的数据项?", "警告", {
      confirmButtonText: "确定",
      cancelButtonText: "取消",
      type: "warning",
    });
  } catch {
    ElMessage.info("已取消删除");
    return;
  }

  loading.value = true;
  try {
    await {$entityName}API.deleteByIds(ids);
    ElMessage.success("删除成功");
    handleResetQuery();
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  handleQuery();
});
</script>

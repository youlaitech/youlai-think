<template>
  <div class="app-container">
    <div class="search-container">
      <el-form ref="queryFormRef" :model="queryParams" :inline="true">
{{searchFormItems}}
        <el-form-item class="search-buttons">
          <el-button type="primary" icon="search" @click="handleQuery">搜索</el-button>
          <el-button icon="refresh" @click="handleResetQuery">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <el-card shadow="never">
      <div class="mb-10px">
        <el-button
          v-hasPerm="['{{moduleName}}:{{entityKebab}}:create']"
          type="success"
          icon="plus"
          @click="handleOpenDialog()"
        >新增</el-button>
        <el-button
          v-hasPerm="['{{moduleName}}:{{entityKebab}}:delete']"
          type="danger"
          :disabled="removeIds.length === 0"
          icon="delete"
          @click="handleDelete()"
        >删除</el-button>
      </div>

      <el-table
        ref="dataTableRef"
        v-loading="loading"
        :data="pageData"
        highlight-current-row
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" align="center" />
{{tableColumns}}
        <el-table-column fixed="right" label="操作" width="220">
          <template #default="scope">
            <el-button
              v-hasPerm="['{{moduleName}}:{{entityKebab}}:update']"
              type="primary"
              size="small"
              link
              icon="edit"
              @click="handleOpenDialog(String(scope.row.id))"
            >
              编辑
            </el-button>
            <el-button
              v-hasPerm="['{{moduleName}}:{{entityKebab}}:delete']"
              type="danger"
              size="small"
              link
              icon="delete"
              @click="handleDelete(String(scope.row.id))"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <pagination
        v-if="total > 0"
        v-model:total="total"
        v-model:page="queryParams.pageNum"
        v-model:limit="queryParams.pageSize"
        @pagination="handleQuery()"
      />
    </el-card>

    <el-dialog
      v-model="dialog.visible"
      :title="dialog.title"
      width="500px"
      @close="handleCloseDialog"
    >
      <el-form ref="dataFormRef" :model="formData" :rules="rules" label-width="100px">
{{formItems}}
      </el-form>
      <template #footer>
        <div class="dialog-footer">
          <el-button type="primary" @click="handleSubmit">确定</el-button>
          <el-button @click="handleCloseDialog">取消</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
defineOptions({
  name: "{{entityName}}",
  inheritAttrs: false,
});

import {{entityName}}API from "@/api/{{moduleName}}/{{entityKebab}}";
import type { {{entityName}}Item, {{entityName}}Form, {{entityName}}QueryParams } from "@/types/api/{{entityKebab}}";

const queryFormRef = ref();
const dataFormRef = ref();

const loading = ref(false);
const removeIds = ref<string[]>([]);
const total = ref(0);

const queryParams = reactive<{{entityName}}QueryParams>({
  pageNum: 1,
  pageSize: 10,
});

const pageData = ref<{{entityName}}Item[]>([]);

const dialog = reactive({
  title: "",
  visible: false,
});

const formData = reactive<{{entityName}}Form>({});

const rules = reactive({
{{rules}}
});

function handleQuery() {
  loading.value = true;
  {{entityName}}API.getPage(queryParams)
    .then((data) => {
      pageData.value = data.list;
      total.value = data.total ?? 0;
    })
    .finally(() => {
      loading.value = false;
    });
}

function handleResetQuery() {
  queryFormRef.value?.resetFields();
  queryParams.pageNum = 1;
  handleQuery();
}

function handleSelectionChange(selection: any) {
  removeIds.value = selection.map((item: any) => String(item.id));
}

function handleOpenDialog(id?: string) {
  dialog.visible = true;
  if (id) {
    dialog.title = "修改{{businessName}}";
    {{entityName}}API.getForm(id).then((data) => {
      Object.assign(formData, data);
    });
  } else {
    dialog.title = "新增{{businessName}}";
  }
}

function handleSubmit() {
  dataFormRef.value?.validate((valid: boolean) => {
    if (!valid) {
      return;
    }

    loading.value = true;
    const id = (formData as any).id as string | undefined;
    const action = id ? {{entityName}}API.update(id, formData) : {{entityName}}API.create(formData);
    action
      .then(() => {
        ElMessage.success(id ? "修改成功" : "新增成功");
        handleCloseDialog();
        handleResetQuery();
      })
      .finally(() => (loading.value = false));
  });
}

function handleCloseDialog() {
  dialog.visible = false;
  dataFormRef.value?.resetFields();
  dataFormRef.value?.clearValidate();
  (formData as any).id = undefined;
}

function handleDelete(id?: string) {
  const ids = [id || removeIds.value].join(",");
  if (!ids) {
    ElMessage.warning("请勾选删除项");
    return;
  }

  ElMessageBox.confirm("确认删除已选中的数据项?", "警告", {
    confirmButtonText: "确定",
    cancelButtonText: "取消",
    type: "warning",
  }).then(
    () => {
      loading.value = true;
      {{entityName}}API.deleteByIds(ids)
        .then(() => {
          ElMessage.success("删除成功");
          handleResetQuery();
        })
        .finally(() => (loading.value = false));
    },
    () => {
      ElMessage.info("已取消删除");
    }
  );
}

onMounted(() => {
  handleQuery();
});
</script>

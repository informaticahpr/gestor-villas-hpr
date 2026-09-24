<script setup lang="ts">
import ToastContainer from './components/ToastContainer.vue'
import DialogHost from './components/DialogHost.vue'
</script>

<template>
  <RouterView />
  <DialogHost />
  <ToastContainer />
</template>

<template>
    <div class="booking-root container py-5">
      <div class="card booking-card mx-auto pt-0 pb-4 px-4 shadow">
        <!-- Header -->
        <div class="mb-4">
          <img
            src="../images/logo.png"
            alt="logo"
            class="rounded"
            style="width: 160px; height: 160px; display: flex; margin: 0 auto;"
          />
          <div>
            <p v-if="$route.path !== '/status' && $route.name !== 'VietQRPage'" class="text-muted fs-6 mb-0 text-center">Xin chào! Chọn gói làm việc của bạn</p>
          </div>
        </div>
  
        <router-view />
      </div>
    </div>
  </template>

<style>
.booking-root {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding-top: 40px;
    box-sizing: border-box;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.booking-card {
    padding: 30px;
    background: #ffffff;
    border-radius: 16px;
    max-width: 780px;
    width: 100%;
    box-shadow: 0 8px 24px rgb(0 0 0 / 0.08);
}

.step-content {
    border-radius: 16px;
    padding: 24px 30px;
    min-height: 380px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.btn {
    border-radius: 8px;
    font-weight: 600;
    transition: background-color 0.25s ease;
}

/* Nút màu chính - màu từ logo */
.btn-warning {
    background-color: #20451F !important;
    border-color: #20451F !;
    color: #fff;
}

.btn-warning:hover:not(:disabled) {
    background-color: #183317;
    border-color: #183317;
    color: #fff;
}

.btn-outline-secondary {
    color: #777;
    border-color: #ccc;
    background-color: transparent;
}

.btn-outline-secondary:hover {
    background-color: #eee;
    color: #333;
    border-color: #bbb;
}

/* Card chọn gói */
.package-card {
    background: #fafafa;
    border: 1.5px solid #ddd;
    border-radius: 12px;
    transition: all 0.3s ease;
    user-select: none;
}

.package-card:hover {
    background: #f0f4fa;
    border-color: #20451F;
    box-shadow: 0 4px 12px rgb(32 69 31 / 0.25);
}

.package-card-selected {
    background: #d9e8ff;
    border-color: #20451F;
    box-shadow: 0 6px 18px rgb(32 69 31 / 0.4);
}

/* Card bàn */
.table-card {
    user-select: none;
    transition: all 0.25s ease;
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 12px;
}

.table-card.table-free {
    background: #fff;
    /* border-color: #b0c4de;
    color: #466d9c; */
    border-color: #ddd;
    color: #333;
    cursor: pointer;
}

.table-card.table-free:hover {
    background: #e1ebfa;
    border-color: #20451F;
    box-shadow: 0 6px 16px rgb(32 69 31 / 0.25);
}

.table-card.table-selected {
    background: #d0defe;
    color: #20451F;
    border-color: #20451F;
    box-shadow: 0 6px 18px rgb(32 69 31 / 0.5);
    cursor: pointer;
    transform: scale(1.04);
    z-index: 10;
}

.table-card.table-occupied {
    background: #f2f2f2;
    color: #999;
    cursor: not-allowed;
}

/* Legend màu */
.legend-box.free {
    display: inline-block;
    width: 20px;
    height: 20px;
    background: #fff;
    border: 2px solid #b0c4de;
    border-radius: 6px;
}

.legend-box.selected {
    display: inline-block;
    width: 20px;
    height: 20px;
    background: #d0defe;
    border: 2px solid #20451F;
    border-radius: 6px;
}

.legend-box.occupied {
    display: inline-block;
    width: 20px;
    height: 20px;
    background: #f2f2f2;
    border: 2px solid #ccc;
    border-radius: 6px;
}

/* Checkbox */
.form-check {
    border-color: #ddd;
    border-radius: 12px;
    border-style: solid;
    border-width: 1.5px;
    background: #fafafa;
    transition: background-color 0.3s ease, border-color 0.3s ease;
}

.form-check-input:checked {
    background-color: #20451F;
    border-color: #20451F;
    margin-top: 0;
}

/* Text màu */
.text-primary {
    color: #20451F !important;
}

.text-warning {
    color: #20451F !important;
}

/* Fade transition */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.btn-momo {
    background-color: #F8579B;
    color: #fff;
    border: none;
    font-weight: 600;
    padding: 12px 20px;
    border-radius: 8px;
    transition: background-color 0.3s ease;
}

.btn-custom {
    background-color: #20451F;
    color: #fff;
    border: none;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 8px;
    transition: background-color 0.3s ease;
}

.extra-item {
    transition: background-color 0.2s ease;
}

.extra-item:hover {
    background-color: #f9f9f9;
}

.extra-item input[type="checkbox"] {
    cursor: pointer;
    width: 18px;
    height: 18px;
}

.quantity-input {
    width: 60px;
    padding: 2px 6px;
    font-size: 0.9rem;
    border-radius: 4px;
    border: 1px solid #ced4da;
    text-align: center;
}

.cursor-pointer {
    cursor: pointer;
}

@media (max-width: 480px) {
    .step-content {
        padding: 0;
    }

    .extra-item {
        padding: 6px 10px;
        font-size: 0.85rem;
        flex-wrap: nowrap;
        overflow-x: auto;
    }

    .extra-item>div.d-flex.align-items-center.flex-grow-1.cursor-pointer {
        flex: 1 1 auto;
        min-width: 120px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .extra-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
    }

    .quantity-input {
        width: 50px;
        font-size: 0.85rem;
        padding: 2px 4px;
        min-width: 50px;
    }

    .extra-item .text-warning {
        font-size: 0.85rem;
        white-space: nowrap;
    }
}
</style>
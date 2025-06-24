import axios from "axios";
import moment from "moment";
import Swal from "sweetalert2";
import { router } from "@inertiajs/vue3";
// axios.defaults.withCredentials = true;

axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

export const formatters = {
  numerilize: (n, t, d) => {
    if (typeof t === undefined || t == null) {
      if (typeof n === "number" || typeof n === "string") {
        if (n > 1000000000000) {
          return (n / 1000000000000).toFixed(d) + " Tn";
        } else if (n > 1000000000) {
          return (n / 1000000000).toFixed(d) + " Bn";
        } else if (n > 1000000) {
          return (n / 1000000).toFixed(d) + " M";
        } else if (n > 1000) {
          return (n / 1000).toFixed(d) + " K";
        }
        return n;
      } else {
        return n;
      }
    } else {
      var num = isFinite(+n) ? +n : 0,
        precession = isFinite(+d) ? Math.abs(d) : 0,
        sep = ",",
        dec = ".",
        toFixedFix = function (num, precession) {
          const k = Math.pow(10, precession);
          return Math.round(num * k) / k;
        },
        s = (precession ? toFixedFix(num, precession) : Math.round(num))
          .toString()
          .split(".");
      if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
      }
      if ((s[1] || "").length < precession) {
        s[1] = s[1] || "";
        s[1] += new Array(precession - s[1].length + 1).join("0");
      }
      return s.join(dec);
    }
  },
  formatDate: (inputDate) => {
    return moment(inputDate).format("ddd, DD/MMM/YYYY h:ma");
  },
  formatJustDate: (inputDate) => {
    return moment(inputDate).format("ddd, DD/MMM/YYYY");
  },
  formatFromNow: (inputDate) => {
    return moment(inputDate).fromNow();
  },
  formatCurrency: (inputCurrency) => {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "UGX",
    }).format(inputCurrency);
  },
  cleanOutSpecialCharacters: (oldVal = "") => {
    return oldVal
      .replace(/[^a-zA-Z0-9\s]/g, "")
      .replace(/\s+/g, "_")
      .toLowerCase();
  },
};

export function getUserRoles() {
  return RequestHelper.getRequest("/api/user-role");
}

export function showLoader(text = "loading..", close = false) {
  Swal.close();
  if (close) {
    Swal.close();
    return;
  }

  Swal.fire({
    title: `<span style='font-size: 18px'>${text}</span>`,
    didOpen: () => {
      Swal.showLoading();
    },
    allowOutsideClick: false,
  });
}

export function hideLoader() {
  Swal.close();
}
export function swalNotification(icon, message) {
  hideLoader();
  const swalWithBootstrapButtons = Swal.mixin({
    customClass: {
      confirmButton: "btn btn-success",
    },
    buttonsStyling: false,
  });
  return swalWithBootstrapButtons.fire({
    icon,
    html: message,
  });
}

export const swalOk = Swal.mixin({
  customClass: {
    confirmButton: "btn btn-success",
  },
  buttonsStyling: false,
});

export const swalConfirm = Swal.mixin({
  customClass: {
    confirmButton: "btn btn-success",
    cancelButton: "btn btn-danger m-2",
  },
  buttonsStyling: false,
});

export const swalDelete = Swal.mixin({
  customClass: {
    confirmButton: "btn btn-danger m-2",
    cancelButton: "btn btn-success",
  },
  buttonsStyling: false,
});

export const number_format = (number, decimalPlaces = 2) => {
  // Round the number to the given number of decimal places
  const roundedNumber = Number(parseFloat(number).toFixed(decimalPlaces));

  // Convert the number to a string with the desired number of decimal places
  const formattedNumber = roundedNumber.toLocaleString(undefined, {
    minimumFractionDigits: decimalPlaces,
    maximumFractionDigits: decimalPlaces,
  });

  return formattedNumber;
};
export const formatDate = (date = "", format = "MMM Do YY") => {
  if (!date.trim()) {
    return moment().format(format);
  }

  return moment(date).format(format);
};

export const periodAgo = (date) => {
  if (!date.trim()) {
    return "";
  }

  return moment(date).fromNow();
};

export const disableInputs = (
  formId,
  enable = false,
  disableButtons = true
) => {
  const form = document.getElementById(formId);
  const elements = form.elements;
  for (var i = 0, len = elements.length; i < len; ++i) {
    if (enable) {
      elements[i].disabled = false;
    } else {
      if (!disableButtons && elements[i].type === "button") {
        continue;
      }
      elements[i].disabled = true;
    }
  }
};
export default {
  methods: {
    convertBytesToKBMB(bytes) {
      if (bytes < 1000) {
        return bytes + " B";
      } else if (bytes < 1000000) {
        const kilobytes = (bytes / 1000).toFixed(2);
        return kilobytes + " KB";
      } else {
        const megabytes = (bytes / 1000000).toFixed(2);
        return megabytes + " MB";
      }
    },
    today() {
      return moment().format("DD/MMM/YYYY");
    },

    days_(date) {
      return moment(date).fromNow();
    },

    formatDate(date) {
      return moment(date).format("DD/MMM/YYYY");
    },

    addMonthsToDate(date, month) {
      return moment(date).add(month, "months").format("YYYY-MM-DD HH:MM");
    },

    goBack() {
      let app = this;
      if (app.step > 1) {
        app.step--;
      } else {
        window.history.back();
      }
    },

    goTo(routeName, id) {
      if (id) {
        router.get(route(routeName, { id: id }));
      } else {
        router.get(route(routeName));
      }
    },

    permissions() {
      return this.$attrs.auth.user.profile.permissions;
    },

    scrollToTop() {
      window.scrollTo({
        top: 0,
        behavior: "smooth",
      });
    },

    isPlainObject(item) {
      return (
        typeof item === "object" && item !== null && item.constructor === Object
      );
    },

    formatSentence(sentence, limit = 25) {
      if (sentence.length > limit) {
        return sentence.substr(0, limit) + "...";
      } else {
        return sentence;
      }
    },

    validateForm(forms, except_columns) {
      for (const form of forms) {
        if (this.hasEmptyValue(form, except_columns)) {
          return true;
        }
      }
      return false;
    },

    isValidEmail(email) {
      // Regular expression for validating email addresses
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    },

    hasEmptyValue(obj, except) {
      for (const key in obj) {
        const value = obj[key];

        if (!except.includes(key)) {
          if (value === "" || value === null) {
            return true;
          }

          // if (typeof value === 'object' && this.hasEmptyValue(value,except)) {
          //     return true;
          // }
        }
      }

      return false; // Return false if all keys have values
    },
  },
};

export function getConflictLevelClassName(level) {
  switch (level.toLowerCase()) {
    case "high":
      return "text-danger";
    case "medium":
      return "text-warning";
    case "low":
      return "text-success";
    default:
      return "text-dark";
  }
}

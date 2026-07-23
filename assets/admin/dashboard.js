(function () {
    "use strict";

    function rootFor(element) {
        return element && element.closest ? element.closest("[data-hpr-billing-admin]") : null;
    }

    function messageFrom(payload, fallback) {
        if (payload && payload.data && typeof payload.data.message === "string") {
            return payload.data.message;
        }
        if (payload && typeof payload.data === "string") {
            return payload.data;
        }
        return fallback;
    }

    function post(root, values) {
        var body = new URLSearchParams(values || {});
        body.set("nonce", root.getAttribute("data-nonce") || "");

        return fetch(root.getAttribute("data-ajax-url") || window.ajaxurl, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: body.toString()
        }).then(function (response) {
            return response.json();
        });
    }

    function toast(root, message, error) {
        var node = root.querySelector("[data-hpr-billing-toast]");
        if (!node) {
            return;
        }
        node.textContent = message;
        node.classList.toggle("is-error", !!error);
        node.classList.add("is-visible");
        window.clearTimeout(node.hprBillingTimer);
        node.hprBillingTimer = window.setTimeout(function () {
            node.classList.remove("is-visible");
        }, 3200);
    }

    document.addEventListener("submit", function (event) {
        var form = event.target.closest("[data-hpr-billing-settings]");
        if (!form) {
            return;
        }
        event.preventDefault();

        var root = rootFor(form);
        var status = form.querySelector("[data-hpr-billing-form-status]");
        var button = form.querySelector("button[type=submit]");
        if (!root || !button) {
            return;
        }

        var values = new URLSearchParams(new FormData(form));
        values.set("action", "hpr_billing_save_settings");
        button.disabled = true;
        if (status) {
            status.textContent = "Saving...";
        }

        post(root, values).then(function (payload) {
            if (!payload || !payload.success) {
                throw new Error(messageFrom(payload, "Settings could not be saved."));
            }
            var message = messageFrom(payload, "Billing settings saved.");
            if (status) {
                status.textContent = message;
            }
            toast(root, message, false);
        }).catch(function (error) {
            if (status) {
                status.textContent = error.message;
            }
            toast(root, error.message, true);
        }).finally(function () {
            button.disabled = false;
        });
    });

    document.addEventListener("click", function (event) {
        var button = event.target.closest("[data-hpr-billing-action]");
        if (!button) {
            return;
        }

        var root = rootFor(button);
        var action = button.getAttribute("data-hpr-billing-action") || "";
        if (!root || !action || button.disabled) {
            return;
        }

        var values = new URLSearchParams();
        values.set("action", action);
        var confirmation = root.querySelector('[data-hpr-billing-confirm="' + action + '"]');
        if (confirmation) {
            values.set("confirmation", confirmation.value || "");
        }

        var panel = button.closest(".hpr-billing-panel") || root;
        var status = panel.querySelector("[data-hpr-billing-action-status]");
        button.disabled = true;
        if (status) {
            status.textContent = "Running...";
        }

        post(root, values).then(function (payload) {
            if (!payload || !payload.success) {
                throw new Error(messageFrom(payload, "Action failed."));
            }
            var message = messageFrom(payload, "Action completed.");
            if (status) {
                status.textContent = message;
            }
            toast(root, message, false);
            window.setTimeout(function () {
                window.location.reload();
            }, 450);
        }).catch(function (error) {
            if (status) {
                status.textContent = error.message;
            }
            toast(root, error.message, true);
            button.disabled = false;
        });
    });
})();


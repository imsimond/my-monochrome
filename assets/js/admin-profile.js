(function ($) {
  "use strict";

  $(function () {
    if (typeof window.mymonoSettings === "undefined") {
      return;
    }

    var settings = window.mymonoSettings;
    var mymonoOption = $('input[value="mymono"]').closest(".color-option");
    var inlineStyleId = settings.styleId || "mymono-admin-colors-inline-css";

    // Timing constants (small delays let WP core handlers run first).
    var CORE_HANDLER_DELAY = 50;
    var REMOVE_DELAY = 20;
    var CLICK_DELAY = 30;
    var PICKER_SUPPRESS_MS = 80;

    if (!mymonoOption.length) {
      return;
    }

    mymonoOption.addClass("mymono-color-option");

    var label = mymonoOption.find("label");
    if (!label.length) {
      return;
    }

    var iconBtn = $(
      '<span class="dashicons dashicons-randomize" title="Randomize colors"></span>',
    );
    var pickBtn = $(
      '<span class="dashicons dashicons-color-picker" title="Choose a color"></span>',
    );

    var pickInput = $(
      '<input type="text" class="mymono-color-picker" value="' +
        settings.baseColor +
        '" style="position:absolute;left:-9999px;"/>',
    );
    mymonoOption.append(pickInput);

    // When we programmatically set the picker's color we must avoid
    // re-entering the change handler which would trigger another AJAX call.
    var suppressPickerChange = false;

    // Track the last color value we POSTed so we can ignore out-of-order responses.
    var lastSentColor = null;

    var updateInlineVars = function (cssVars) {
      if (!cssVars) {
        return;
      }

      var styleEl = document.getElementById(inlineStyleId);
      if (!styleEl) {
        styleEl = document.createElement("style");
        styleEl.id = inlineStyleId;
        document.head.appendChild(styleEl);
      }

      styleEl.textContent = cssVars;
    };

    var ensureStylesheet = function () {
      if (!settings.cssUrl) {
        return;
      }

      var linkId = "mymono-admin-colors-css";
      var existing = document.getElementById(linkId);
      if (existing) {
        if (existing.getAttribute("href") !== settings.cssUrl) {
          existing.setAttribute("href", settings.cssUrl);
        }
        return existing;
      }

      var link = document.createElement("link");
      link.id = linkId;
      link.rel = "stylesheet";
      link.href = settings.cssUrl;
      document.head.appendChild(link);
      return link;
    };

    var removeStylesheet = function () {
      var link = document.getElementById("mymono-admin-colors-css");
      if (link && link.parentNode) {
        link.parentNode.removeChild(link);
      }
    };

    var updateShade = function (baseColor) {
      var shadeBox = mymonoOption.find(".color-palette-shade");
      shadeBox.css("background-color", baseColor);
      shadeBox.attr("title", baseColor);
    };

    var removeInlineVars = function () {
      var styleEl = document.getElementById(inlineStyleId);
      if (styleEl && styleEl.parentNode) {
        styleEl.parentNode.removeChild(styleEl);
      }
      $("body").removeClass("admin-color-mymono");
    };

    var applyMymonoStyles = function () {
      clearAdminColorClasses();
      ensureStylesheet();
      updateInlineVars(settings.cssVars);
      $("body").addClass("admin-color-mymono");
    };

    var removeMymonoStyles = function () {
      removeInlineVars();
      removeStylesheet();
    };

    // Helper to remove any existing admin-color- classes.
    var clearAdminColorClasses = function () {
      $("body").removeClass(function (index, className) {
        var matches = className.match(/(^|\s)admin-color-\S+/g) || [];
        return matches.join(" ");
      });
    };

    // Apply initial inline vars if mymono is already selected on load.
    if (mymonoOption.find("input[type=radio]").is(":checked")) {
      applyMymonoStyles();
    }

    // Listen for admin color radio changes and apply/remove inline CSS —
    // use a short delay so WP core handlers run first (they may modify body classes).
    $(document).on("change", 'input[name="admin_color"]', function () {
      var val = $(this).val();
      if (val === "mymono") {
        setTimeout(function () {
          applyMymonoStyles();
        }, CORE_HANDLER_DELAY);
      } else {
        // delay slightly to ensure any core changes finish before we remove our styles
        setTimeout(function () {
          removeMymonoStyles();
        }, REMOVE_DELAY);
      }
    });

    // Ensure clicks on the color-option container also apply our styles
    // (WP core may handle container clicks differently than direct radio clicks).
    mymonoOption.on("click", function (event) {
      // run after core's handlers
      setTimeout(function () {
        var radio = mymonoOption.find('input[name="admin_color"]');
        if (!radio.length) {
          return;
        }
        if (radio.is(":checked")) {
          applyMymonoStyles();
        }
      }, CLICK_DELAY);
    });

    var handlePaletteResponse = function (response) {
      if (!response || !response.success) {
        return;
      }

      // If we recently sent a color request, ignore responses that don't
      // match the last color we asked for (prevents out-of-order responses).
      if (
        lastSentColor !== null &&
        response.data.base.toLowerCase() !== lastSentColor.toLowerCase()
      ) {
        return;
      }

      // Persist the latest CSS vars and base color into settings so
      // re-applying the scheme later uses the updated palette.
      if (typeof settings !== "undefined") {
        settings.cssVars = response.data.cssVars;
        settings.baseColor = response.data.base;
      }

      updateInlineVars(response.data.cssVars);
      updateShade(response.data.base);

      // keep the color picker in sync if present, but suppress its change
      // handler while we programmatically update it to avoid loops.
      if (pickInput && pickInput.wpColorPicker) {
        try {
          suppressPickerChange = true;
          pickInput.wpColorPicker("color", response.data.base);
          setTimeout(function () {
            suppressPickerChange = false;
          }, PICKER_SUPPRESS_MS);
        } catch (e) {
          // ignore if picker not yet initialized
        }
      }

      // Clear lastSentColor now we've applied the response.
      lastSentColor = null;
    };

    pickInput.wpColorPicker({
      hide: true,
      clear: false,
      change: function (event, ui) {
        if (suppressPickerChange) {
          return;
        }
        var newColor = ui.color.toString();
        lastSentColor = newColor;
        $.post(
          ajaxurl,
          {
            action: "mymono_set_palette_ajax",
            _wpnonce: settings.setNonce,
            color: newColor,
            user_id: settings.userId,
          },
          handlePaletteResponse,
        );
      },
    });

    pickBtn.on("click", function (event) {
      event.preventDefault();
      pickInput.iris("toggle");
    });

    $(document).on("mousedown.mymonoColorPicker", function (event) {
      var pickerContainer = pickInput.closest(".wp-picker-container");
      var target = $(event.target);

      if (!pickerContainer.length) {
        return;
      }

      if (!pickerContainer.find(".iris-picker").is(":visible")) {
        return;
      }

      if (target.closest(".wp-picker-container").length) {
        return;
      }

      if (target.closest(".dashicons-color-picker").length) {
        return;
      }

      if (target.closest(".mymono-color-picker").length) {
        return;
      }

      pickInput.iris("hide");
    });

    iconBtn.on("click", function (event) {
      event.preventDefault();
      lastSentColor = null;
      $.post(
        ajaxurl,
        {
          action: "mymono_randomize_palette_ajax",
          _wpnonce: settings.randomNonce,
          user_id: settings.userId,
        },
        function (response) {
          handlePaletteResponse(response);
          // picker will be updated via handlePaletteResponse
        },
      );
    });

    label.append(iconBtn, pickBtn);
  });
})(jQuery);

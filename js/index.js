document.addEventListener("DOMContentLoaded", () => {

	const toggle = document.querySelector(".mobile-toggle");
	const navigation = document.querySelector(".site-nav");

	if (!toggle || !navigation) {
		return;
	}


	/**
	 * Open or close the mobile navigation.
	 */
	const setNavigationState = (open) => {

		toggle.setAttribute("aria-expanded", String(open));

		toggle.setAttribute(
			"aria-label",
			open ? "Close navigation" : "Open navigation"
		);

		navigation.classList.toggle("is-open", open);
		document.body.classList.toggle("nav-open", open);

	};


	/**
	 * Mobile menu button.
	 */
	toggle.addEventListener("click", () => {

		const isOpen =
			toggle.getAttribute("aria-expanded") === "true";

		setNavigationState(!isOpen);

	});


	/**
	 * Close the mobile menu after selecting a link.
	 */
	navigation.querySelectorAll("a").forEach((link) => {

		link.addEventListener("click", () => {

			if (window.innerWidth <= 950) {
				setNavigationState(false);
			}

		});

	});


	/**
	 * Escape closes the navigation.
	 */
	document.addEventListener("keydown", (event) => {

		if (event.key !== "Escape") {
			return;
		}

		if (toggle.getAttribute("aria-expanded") === "true") {

			setNavigationState(false);
			toggle.focus();

		}

	});


	/**
	 * Reset mobile navigation if the browser is resized
	 * back to desktop width.
	 */
	window.addEventListener("resize", () => {

		if (window.innerWidth > 950) {
			setNavigationState(false);
		}

	});

});
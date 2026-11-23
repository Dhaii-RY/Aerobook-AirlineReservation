document.addEventListener("DOMContentLoaded", () => {
    const seats = [...document.querySelectorAll(".seat-button.is-available")];
    const form = document.getElementById("seatForm");
    const label = document.getElementById("selectedSeatLabel");
    const total = document.getElementById("selectedSeatTotal");
    const fields = document.getElementById("passengerFields");
    const continueButton = document.getElementById("continueButton");
    const selected = new Map();

    function render() {
        const choices = [...selected.values()];
        label.textContent = choices.length
            ? choices.map(seat => seat.number).join(", ")
            : "Belum dipilih";
        total.textContent = `${choices.length} kursi dipilih`;

        form.querySelectorAll('input[name="id_kursi_penerbangan[]"]').forEach(el => el.remove());
        choices.forEach(seat => {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = "id_kursi_penerbangan[]";
            input.value = seat.id;
            form.appendChild(input);
        });

        fields.replaceChildren();
        if (!choices.length) {
            const empty = document.createElement("p");
            empty.className = "summary-muted";
            empty.textContent = "Belum ada kursi yang dipilih.";
            fields.appendChild(empty);
        }

        choices.forEach((seat, index) => {
            const group = document.createElement("section");
            group.className = "passenger-seat-fields";
            const heading = document.createElement("h3");
            heading.textContent = `Penumpang ${index + 1} — Kursi ${seat.number}`;
            group.appendChild(heading);

            [
                ["Nama lengkap", "nama_penumpang", "text", 2, 100],
                ["Alamat email", "email", "email", 0, 150],
                ["Nomor identitas", "nomor_paspor_nik", "text", 5, 50]
            ].forEach(([caption, name, type, min, max]) => {
                const wrapper = document.createElement("div");
                wrapper.className = "passenger-field";
                const inputId = `${name}_${seat.id}`;
                const labelEl = document.createElement("label");
                labelEl.htmlFor = inputId;
                labelEl.textContent = caption;
                const input = document.createElement("input");
                input.type = type;
                input.id = inputId;
                input.name = `${name}[]`;
                input.required = true;
                input.maxLength = max;
                if (min) input.minLength = min;
                input.autocomplete = "off";
                wrapper.append(labelEl, input);
                group.appendChild(wrapper);
            });
            fields.appendChild(group);
        });

        continueButton.disabled = choices.length === 0;
    }

    seats.forEach(button => {
        button.setAttribute("aria-pressed", "false");
        button.addEventListener("click", () => {
            const id = button.dataset.seatId;
            if (selected.has(id)) {
                selected.delete(id);
                button.classList.remove("is-selected");
                button.setAttribute("aria-pressed", "false");
            } else {
                selected.set(id, {
                    id,
                    number: button.dataset.seatNumber
                });
                button.classList.add("is-selected");
                button.setAttribute("aria-pressed", "true");
            }
            render();
        });
    });

    form.addEventListener("submit", event => {
        if (!selected.size) {
            event.preventDefault();
            alert("Pilih minimal satu kursi.");
        }
    });
});

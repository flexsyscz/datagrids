import naja from "naja";

export default function() {
	Array.from(document.querySelectorAll('[data-fxs-toggle="adjustColumns"]')).forEach((el) => {
		el.addEventListener('click', (e) => {
			e.preventDefault()

			if (!el.dataset.state) {
				el.dataset.state = 'true';
			}

			const state = el.dataset.state === 'true';
			Array.from(el.closest('.dropdown-menu').querySelectorAll('.adjustable-columns input[type=checkbox]')).forEach((el) => {
				el.checked = state

			});

			el.dataset.state = state ? 'false' : 'true'
		})
	})


	Array.from(document.querySelectorAll('input[type="checkbox"][data-fxs-toggle="allItems"]')).forEach((checkbox) => {
		checkbox.addEventListener('change', (e) => {
			Array.from(checkbox.closest('.datagrid').querySelectorAll('td.selectable input[name^=item]')).forEach((el) => {
				el.checked = checkbox.checked
			})

			const url = checkbox.dataset.fxsUrl
			naja.makeRequest('POST', url, {state: checkbox.checked}, {history: false}).then((payload) => {
				const root = checkbox.closest('.datagrid')
				Array.from(root.querySelectorAll('tr[id^=item]')).forEach((tr) => {
					tr.classList.remove('table-success')
				})

				if (payload.selectedItems) {
					for (const itemId in payload.selectedItems) {
						Array.from(root.querySelectorAll('tr#item' + itemId)).forEach((tr) => {
							tr.classList.add('table-success')
						})
					}
				}
			}).catch((err) => {
				alert(err)
			})
		})
	})

	Array.from(document.querySelectorAll('input[type="checkbox"][data-fxs-toggle="item"]')).forEach((checkbox) => {
		checkbox.addEventListener('change', (e) => {
			const url = checkbox.dataset.fxsUrl
			naja.makeRequest('POST', url, {state: checkbox.checked}, {history: false}).then((payload) => {
				if (payload.selected) {
					checkbox.closest('tr').classList.add('table-success')
				} else {
					checkbox.closest('tr').classList.remove('table-success')
				}
			}).catch((err) => {
				alert(err)
			})
		})
	})
}

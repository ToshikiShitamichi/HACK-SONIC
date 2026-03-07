$(function () {
    // -------------------------------
    // 予算スライダー
    // -------------------------------
    $("#budgetSlider").slider({
        range: true,
        min: 0,
        max: 300000,
        step: 1000,
        values: [0, 100000],
        slide: function (event, ui) {
            $("#budgetMin").val(ui.values[0]);
            $("#budgetMax").val(ui.values[1]);
            $("#budgetMinText").text(ui.values[0].toLocaleString() + "円");
            $("#budgetMaxText").text(ui.values[1].toLocaleString() + "円");
        }
    });

    // -------------------------------
    // 共通
    // -------------------------------
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function resetCitySelect($select, placeholder = "都道府県を先に選択してください") {
        $select.empty();
        $select.append(`<option value="">${placeholder}</option>`);
    }

    function getWideAreaLabel(prefectureName) {
        if (prefectureName === "北海道") return "道全域";
        if (prefectureName === "東京都") return "都全域";
        if (prefectureName === "大阪府" || prefectureName === "京都府") return "府全域";
        return "県全域";
    }

    function fillCitySelect($select, cities, includeAllArea = false, prefectureName = "") {
        $select.empty();
        $select.append('<option value="">選択してください</option>');

        if (includeAllArea) {
            $select.append(
                `<option value="${escapeHtml(getWideAreaLabel(prefectureName))}">${escapeHtml(getWideAreaLabel(prefectureName))}</option>`
            );
        }

        cities.forEach(cityName => {
            $select.append(
                `<option value="${escapeHtml(cityName)}">${escapeHtml(cityName)}</option>`
            );
        });
    }

    // -------------------------------
    // HeartRails Geo API
    // -------------------------------
    async function fetchCitiesByPrefecture(prefectureName) {
        const url = `https://geoapi.heartrails.com/api/json?method=getCities&prefecture=${encodeURIComponent(prefectureName)}`;
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error("市区町村一覧の取得に失敗しました。");
        }

        const data = await response.json();

        // HeartRails: response.location = [{ city, city-kana }, ...]
        const locations = data?.response?.location || [];

        return locations.map(item => item.city).filter(Boolean);
    }

    async function searchAddressByGeoLocation(lat, lon) {
        // HeartRails は x=経度, y=緯度
        const url = `https://geoapi.heartrails.com/api/json?method=searchByGeoLocation&x=${encodeURIComponent(lon)}&y=${encodeURIComponent(lat)}`;
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error("位置情報から住所を取得できませんでした。");
        }

        const data = await response.json();
        const locations = data?.response?.location || [];

        if (!locations.length) {
            throw new Error("住所候補が見つかりませんでした。");
        }

        // 先頭候補を採用
        return locations[0];
    }

    // -------------------------------
    // 出発地 市区町村ロード
    // -------------------------------
    async function loadDepartureCities(prefectureName, selectedCity = "") {
        const $select = $("#departureCity");

        if (!prefectureName) {
            resetCitySelect($select);
            return;
        }

        $select.prop("disabled", true);
        resetCitySelect($select, "読み込み中...");

        try {
            const cities = await fetchCitiesByPrefecture(prefectureName);
            fillCitySelect($select, cities, false, prefectureName);

            if (selectedCity) {
                $select.val(selectedCity);

                if (!$select.val()) {
                    const matched = cities.find(city =>
                        city === selectedCity ||
                        city.includes(selectedCity) ||
                        selectedCity.includes(city)
                    );
                    if (matched) {
                        $select.val(matched);
                    }
                }
            }
        } catch (error) {
            console.error(error);
            resetCitySelect($select, "市区町村の取得に失敗しました");
        } finally {
            $select.prop("disabled", false);
        }
    }

    // -------------------------------
    // 旅行先 市区町村ロード
    // -------------------------------
    async function loadDestinationCities(prefectureName, selectedCity = "") {
        const $select = $("#destinationCity");

        if (!prefectureName) {
            resetCitySelect($select);
            return;
        }

        $select.prop("disabled", true);
        resetCitySelect($select, "読み込み中...");

        try {
            const cities = await fetchCitiesByPrefecture(prefectureName);
            fillCitySelect($select, cities, true, prefectureName);

            if (selectedCity) {
                $select.val(selectedCity);

                if (!$select.val()) {
                    const matched = cities.find(city =>
                        city === selectedCity ||
                        city.includes(selectedCity) ||
                        selectedCity.includes(city)
                    );
                    if (matched) {
                        $select.val(matched);
                    }
                }
            }
        } catch (error) {
            console.error(error);
            resetCitySelect($select, "市区町村の取得に失敗しました");
        } finally {
            $select.prop("disabled", false);
        }
    }

    // -------------------------------
    // 都道府県変更イベント
    // -------------------------------
    $("#departurePrefecture").on("change", function () {
        const prefectureName = $(this).val();
        loadDepartureCities(prefectureName);
    });

    $("#destinationPrefecture").on("change", function () {
        const prefectureName = $(this).val();
        loadDestinationCities(prefectureName);
    });

    // -------------------------------
    // 現在地取得
    // -------------------------------
    $("#getCurrentLocationButton").on("click", function () {
        $("#getCurrentLocationButton").prop("disabled", true);
        const $message = $("#getCurrentLocationButton");
        $message.text("現在地を取得しています...");

        if (!navigator.geolocation) {
            alert("このブラウザは位置情報取得に対応していません。");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            async function (position) {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;

                try {
                    const location = await searchAddressByGeoLocation(lat, lon);

                    const prefecture = location.prefecture || "";
                    const city = location.city || "";

                    if (!prefecture) {
                        alert("都道府県を特定できませんでした。");
                        return;
                    }

                    $("#departurePrefecture").val(prefecture);

                    await loadDepartureCities(prefecture, city);

                    $("#getCurrentLocationButton").text("現在地を取得");
                    $("#getCurrentLocationButton").prop("disabled", false);

                } catch (error) {
                    console.error(error);
                    alert("現在地の住所変換に失敗しました。");
                }
            },
            function (error) {
                console.error(error);
                alert("現在地の取得に失敗しました。位置情報の許可を確認してください。");
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    });
});